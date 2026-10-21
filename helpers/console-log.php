<?php

function psm_console_log($data, $label = '')
{
    global $psm_custom_logs;
    if (!is_array($psm_custom_logs)) {
        $psm_custom_logs = [];
    }
    $psm_custom_logs[] = ['label' => $label, 'data' => $data];
}

add_action('admin_footer', function () {
    global $psm_custom_logs;
    if (!empty($psm_custom_logs)) {
        echo "<script>\n";
        echo "console.group('🛠️ Picoteo Store Manager Logs');\n";
        foreach ($psm_custom_logs as $log) {
            $json = json_encode($log['data']);
            if (!empty($log['label'])) {
                $label = json_encode($log['label']);
                echo "console.log({$label}, {$json});\n";
            } else {
                echo "console.log({$json});\n";
            }
        }
        echo "console.groupEnd();\n";
        echo "</script>\n";
        $psm_custom_logs = [];
    }
});
