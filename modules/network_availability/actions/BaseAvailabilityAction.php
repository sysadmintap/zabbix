<?php
namespace Modules\NetworkAvailability\Actions;

use CController;

abstract class BaseAvailabilityAction extends CController {

    /**
     * ------------------------------------------------------------------
     * CONFIG: what counts as "device down"
     * ------------------------------------------------------------------
     * Confirmed via:
     *   Huawei VRP: Unavailable by ICMP ping
     *   ICMP Ping: Unavailable by ICMP ping
     * Interface-level "Link down" triggers are deliberately excluded - a
     * single interface flapping isn't the same as the whole device being
     * unreachable.
     */
    const DOWN_TRIGGER_PATTERN = '%Unavailable by ICMP ping%';

    protected function init() {
        $this->disableCsrfValidation();
    }

    protected function checkPermissions() {
        return true; // tighten this to your actual permission model
    }

    /**
     * Common input validation shared by the view page and both exports.
     */
    protected function commonFields(): array {
        return [
            'from'     => 'string',
            'to'       => 'string',
            'hostids'  => 'array_id',
            'groupids' => 'array_id',
        ];
    }

    /**
     * Resolve from/to/hostids/groupids inputs, defaulting to the last 7 days.
     * Returns [from_ts, to_ts, from_str, to_str, hostids, groupids].
     */
    protected function resolveFilters(): array {
        $from_input = $this->getInput('from', date('Y-m-d H:i:s', strtotime('-7 days')));
        $to_input   = $this->getInput('to', date('Y-m-d H:i:s'));

        $from = strtotime($from_input) ?: strtotime('-7 days');
        $to   = strtotime($to_input) ?: time();

        $hostids  = $this->getInput('hostids', []);
        $groupids = $this->getInput('groupids', []);

        return [$from, $to, $from_input, $to_input, $hostids, $groupids];
    }

    /**
     * Build one row per host: device, ip, availability %, downtime, down count.
     */
    protected function calculateAvailability($from, $to, array $hostids = [], array $groupids = []) {
        $hosts = $this->getHosts($hostids, $groupids);
        $rows = [];

        foreach ($hosts as $host) {
            $events = $this->getDownEvents($host['hostid'], $from, $to);
            [$downtimeSeconds, $downCount] = $this->summarizeDowntime($events, $from, $to);

            $period = $to - $from;
            $availabilityPct = $period > 0
                ? round((($period - $downtimeSeconds) / $period) * 100, 2)
                : 100.0;

            $rows[] = [
                'device'       => $host['host'],
                'ip'           => $host['ip'],
                'availability' => $availabilityPct,
                'downtime'     => $this->formatDuration($downtimeSeconds),
                'down_count'   => $downCount,
            ];
        }

        return $rows;
    }

    /**
     * Enabled, non-prototype hosts with an interface IP, optionally filtered
     * to specific hostids and/or host groups.
     */
    protected function getHosts(array $hostids = [], array $groupids = []) {
        $sql = 'SELECT DISTINCT h.hostid, h.name AS host, i.ip'
             . ' FROM hosts h'
             . ' JOIN interface i ON i.hostid = h.hostid';

        if ($groupids) {
            $sql .= ' JOIN hosts_groups hg ON hg.hostid = h.hostid';
        }

        $sql .= ' WHERE h.status = 0'
              . ' AND h.flags = 0'; // exclude host prototypes (4) and discovered hosts (2)

        if ($hostids) {
            $sql .= ' AND h.hostid IN (' . implode(',', array_map('intval', $hostids)) . ')';
        }

        if ($groupids) {
            $sql .= ' AND hg.groupid IN (' . implode(',', array_map('intval', $groupids)) . ')';
        }

        $result = \DBselect($sql);
        $hosts = [];
        while ($row = \DBfetch($result)) {
            $hosts[] = $row;
        }
        return $hosts;
    }

    /**
     * All host groups, for the filter dropdown.
     */
    protected function getHostGroups() {
        $sql = 'SELECT groupid, name FROM hstgrp ORDER BY name';

        $result = \DBselect($sql);
        $groups = [];
        while ($row = \DBfetch($result)) {
            $groups[] = $row;
        }
        return $groups;
    }

    /**
     * Problem events on the "down" trigger for a host, overlapping [from, to].
     */
    protected function getDownEvents($hostid, $from, $to) {
        $now = time();

        $sql = 'SELECT p.eventid, p.clock AS start_clock,'
             . '       COALESCE(r.clock, ' . \zbx_dbstr($now) . ') AS end_clock'
             . ' FROM problem p'
             . ' JOIN triggers t ON t.triggerid = p.objectid'
             . ' JOIN functions f ON f.triggerid = t.triggerid'
             . ' JOIN items i ON i.itemid = f.itemid'
             . ' LEFT JOIN event_recovery er ON er.eventid = p.eventid'
             . ' LEFT JOIN events r ON r.eventid = er.r_eventid'
             . ' WHERE i.hostid = ' . \zbx_dbstr($hostid)
             . ' AND t.description LIKE ' . \zbx_dbstr(self::DOWN_TRIGGER_PATTERN)
             . ' AND p.clock < ' . \zbx_dbstr($to)
             . ' AND (er.r_eventid IS NULL OR r.clock > ' . \zbx_dbstr($from) . ')';

        $result = \DBselect($sql);
        $events = [];
        while ($row = \DBfetch($result)) {
            $events[] = $row;
        }
        return $events;
    }

    /**
     * Clip each event to [from, to] and sum, deduping by eventid.
     */
    protected function summarizeDowntime(array $events, $from, $to) {
        $seen = [];
        $totalDown = 0;
        $count = 0;

        foreach ($events as $e) {
            if (isset($seen[$e['eventid']])) {
                continue;
            }
            $seen[$e['eventid']] = true;

            $start = max((int) $e['start_clock'], $from);
            $end   = min((int) $e['end_clock'], $to);

            if ($end > $start) {
                $totalDown += ($end - $start);
                $count++;
            }
        }

        return [$totalDown, $count];
    }

    protected function formatDuration($seconds) {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);

        if ($h > 0) {
            return sprintf('%dh %dm', $h, $m);
        }
        return sprintf('%dm', $m);
    }
}
