<?php
/**
 * @var CView $this
 * @var array $data   Provided by Report.php's doAction():
 *                     ['rows' => [...], 'from' => int, 'to' => int,
 *                      'from_str' => string, 'to_str' => string, 'hostids' => array]
 */

$this->addJsFile('class.calendar.js');

// --- Filter form -----------------------------------------------------
$group_select = (new CTag('select', true))
    ->setAttribute('name', 'groupids[]')
    ->setAttribute('multiple', 'multiple')
    ->setAttribute('size', 6)
    ->setId('groupids')
    ->addClass('multiline-select'); // plain native listbox, no extra JS assets required

foreach ($data['groups'] as $group) {
    $option = (new CTag('option', true, $group['name']))
        ->setAttribute('value', $group['groupid']);

    if (in_array($group['groupid'], $data['groupids'])) {
        $option->setAttribute('selected', 'selected');
    }

    $group_select->addItem($option);
}

$filter_form = (new CForm('get'))
    ->addVar('action', 'network.availability')
    ->addItem(
        (new CFormList())
            ->addRow(
                _('Device Group'),
                $group_select
            )
            ->addRow(
                _('From'),
                (new CDateSelector('from', $data['from_str']))
                    ->setDateFormat(ZBX_DATE_TIME)
                    ->setPlaceholder(ZBX_DATE_TIME)
            )
            ->addRow(
                _('To'),
                (new CDateSelector('to', $data['to_str']))
                    ->setDateFormat(ZBX_DATE_TIME)
                    ->setPlaceholder(ZBX_DATE_TIME)
            )
    )
    ->addItem(
        (new CSubmitButton(_('Filter'), 'filter_set', 1))->addClass(ZBX_STYLE_BTN_ALT)
    );

// --- Export buttons: separate small forms so each can post to its own action,
// carrying the same filter values currently applied. ---------------------
function buildExportForm(string $action, string $label, array $data): CForm {
    $form = (new CForm('get'))
        ->addVar('action', $action)
        ->addVar('from', $data['from_str'])
        ->addVar('to', $data['to_str']);

    foreach ($data['groupids'] as $groupid) {
        $form->addVar('groupids[]', $groupid);
    }
    foreach ($data['hostids'] as $hostid) {
        $form->addVar('hostids[]', $hostid);
    }

    return $form->addItem(
        (new CSubmitButton($label))->addClass(ZBX_STYLE_BTN_ALT)
    );
}

$export_buttons = (new CDiv([
    buildExportForm('network.availability.csv', _('Download CSV'), $data),
    buildExportForm('network.availability.pdf', _('Download PDF'), $data),
]))->addClass('export-buttons');

// --- Results table -----------------------------------------------------
$table = (new CTableInfo())
    ->setHeader([
        _('Device'),
        _('Hostname'),
        _('Availability'),
        _('Total Downtime'),
        _('Down Count'),
    ]);

foreach ($data['rows'] as $row) {
    $table->addRow([
        $row['device'],
        $row['ip'],
        sprintf('%.2f%%', $row['availability']),
        $row['downtime'],
        $row['down_count'],
    ]);
}

(new CHtmlPage())
    ->setTitle(_('Network Availability Report'))
    ->addItem($filter_form)
    ->addItem($export_buttons)
    ->addItem($table)
    ->show();
