<?php
namespace Modules\NetworkAvailability\Actions;

use CControllerResponseData;
use CControllerResponseFatal;

class Report extends BaseAvailabilityAction {

    protected function checkInput() {
        $ret = $this->validateInput($this->commonFields());

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function doAction() {
        [$from, $to, $from_input, $to_input, $hostids, $groupids] = $this->resolveFilters();

        if ($from === false || $to === false || $to <= $from) {
            $this->setResponse(new CControllerResponseData([
                'main_block' => json_encode(['error' => 'Invalid date range'])
            ]));
            return;
        }

        $rows = $this->calculateAvailability($from, $to, $hostids, $groupids);

        $data = [
            'rows'     => $rows,
            'from'     => $from,
            'to'       => $to,
            'from_str' => $from_input,
            'to_str'   => $to_input,
            'hostids'  => $hostids,
            'groupids' => $groupids,
            'groups'   => $this->getHostGroups(),
        ];

        $this->setResponse(new CControllerResponseData($data));
    }
}
