<?php
namespace Modules\NetworkAvailability\Actions;

use CControllerResponseFatal;

class ExportCsv extends BaseAvailabilityAction {

    protected function checkInput() {
        $ret = $this->validateInput($this->commonFields());

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function doAction() {
        [$from, $to, , , $hostids, $groupids] = $this->resolveFilters();

        $rows = $this->calculateAvailability($from, $to, $hostids, $groupids);

        $filename = 'network_availability_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Device', 'Hostname', 'Availability', 'Total Downtime', 'Down Count']);

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['device'],
                $row['ip'],
                sprintf('%.2f%%', $row['availability']),
                $row['downtime'],
                $row['down_count'],
            ]);
        }

        fclose($out);
        session_write_close();
        exit();
    }
}
