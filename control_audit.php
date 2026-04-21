<?php
header('Content-Type: application/json');
$controlFile = __DIR__ . '/control.json';
$progressFile = __DIR__ . '/progress.json';
$pidFile = __DIR__ . '/audit.pid';
$action = $_GET['action'] ?? '';

// Helper to check if PID is actually running on Windows
function isProcessRunning($pid) {
    if (!$pid || !is_numeric($pid)) return false;
    $output = [];
    exec("tasklist /FI \"PID eq $pid\" /NH", $output);
    // Tasklist returns information if process exists
    foreach ($output as $line) {
        if (strpos($line, (string)$pid) !== false) return true;
    }
    return false;
}

if ($action === 'stop') {
    file_put_contents($controlFile, json_encode(['action' => 'stop']));
    
    // OPTIMISTIC UI: Immediately set status to IDLE to unlock the Start button
    if (file_exists($progressFile)) {
        $p = json_decode(file_get_contents($progressFile), true);
        $p['status'] = 'IDLE';
        file_put_contents($progressFile, json_encode($p));
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Stop signal sent. Dashboard updated instantly.']);
    exit;
}

if ($action === 'start') {
    // REALITY CHECK: Is there a living process?
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if (isProcessRunning($pid)) {
            echo json_encode(['status' => 'error', 'message' => 'AI Engine is ALREADY running (PID: '.$pid.')']);
            exit;
        }
    }

    // Set control to run and optimistic status to RUNNING
    file_put_contents($controlFile, json_encode(['action' => 'run']));
    if (file_exists($progressFile)) {
        $p = json_decode(file_get_contents($progressFile), true);
        $p['status'] = 'RUNNING';
        file_put_contents($progressFile, json_encode($p));
    }
    
    // Launch node with explicit directory context
    try {
        $dir = __DIR__;
        if (class_exists('COM')) {
            $WshShell = new COM("WScript.Shell");
            $cmd = "cmd /c cd /d \"$dir\" && node audit_engine.js";
            $WshShell->Run($cmd, 0, false);
            echo json_encode(['status' => 'success', 'message' => 'AI Engine started']);
        } else {
            pclose(popen("start /B cmd /c cd /d \"$dir\" && node audit_engine.js", "r"));
            echo json_encode(['status' => 'success', 'message' => 'AI Engine started (Detached)']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to launch: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
