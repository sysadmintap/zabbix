<?php
namespace Modules\NetworkAvailability\Actions;

use CControllerResponseFatal;

class ExportPdf extends BaseAvailabilityAction {

    protected function checkInput() {
        $ret = $this->validateInput($this->commonFields());

        if (!$ret) {
            $this->setResponse(new CControllerResponseFatal());
        }

        return $ret;
    }

    protected function doAction() {
        [$from, $to, $from_input, $to_input, $hostids, $groupids] = $this->resolveFilters();

        $rows = $this->calculateAvailability($from, $to, $hostids, $groupids);

        // mPDF is installed separately via Composer inside this module's folder -
        // see deployment notes. Not bundled with Zabbix itself.
        $autoload = __DIR__ . '/../vendor/autoload.php';

        if (!file_exists($autoload)) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo "PDF export is not set up yet.\n"
               . "Run 'composer require mpdf/mpdf' inside the network_availability module folder, then try again.";
            session_write_close();
            exit();
        }

        require_once $autoload;

        $html = '<h2>Network Availability Report</h2>'
              . '<p>' . htmlspecialchars($from_input) . ' &ndash; ' . htmlspecialchars($to_input) . '</p>'
              . '<table border="1" cellpadding="5" cellspacing="0" width="100%">'
              . '<thead><tr>'
              . '<th>Device</th><th>Hostname</th><th>Availability</th><th>Total Downtime</th><th>Down Count</th>'
              . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars($row['device']) . '</td>'
                . '<td>' . htmlspecialchars($row['ip']) . '</td>'
                . '<td>' . sprintf('%.2f%%', $row['availability']) . '</td>'
                . '<td>' . htmlspecialchars($row['downtime']) . '</td>'
                . '<td>' . (int) $row['down_count'] . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';

        $tmp_dir = sys_get_temp_dir() . '/mpdf';
        if (!is_dir($tmp_dir)) {
            mkdir($tmp_dir, 0777, true);
        }

        $mpdf = new \Mpdf\Mpdf(['tempDir' => $tmp_dir]);
        $mpdf->WriteHTML($html);

        $filename = 'network_availability_' . date('Ymd_His') . '.pdf';
        $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

        session_write_close();
        exit();
    }
}
