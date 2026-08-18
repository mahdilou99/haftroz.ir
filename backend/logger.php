<?php
class AppLogger {
    private $logFile;

    public function __construct($filename = 'app.log') {
        $this->logFile = __DIR__ . '/' . $filename;
    }

    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can be a comma-separated list, take the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }

    public function log($level, $message, $context = []) {
        $date = date('Y-m-d H:i:s');
        $ip = $this->getClientIP();
        
        $contextString = '';
        if (!empty($context)) {
            $contextString = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        $logEntry = "[$date] [$level] [IP: $ip] $message$contextString" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    public function info($message, $context = []) {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = []) {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('WARNING', $message, $context);
    }
}
?>
