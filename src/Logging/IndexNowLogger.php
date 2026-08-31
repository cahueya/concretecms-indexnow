<?php
namespace Concrete\Package\Indexnow\Logging;

use Concrete\Core\Support\Facade\Config;
use Concrete\Core\Support\Facade\Log;

class IndexNowLogger
{
    public function debug($message, array $context = [])
    {
        if (!Config::get('indexnow.debug_logging')) {
            return;
        }
        $this->write('DEBUG', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->write('WARNING', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->write('ERROR', $message, $context);
    }

    protected function write($level, $message, array $context)
    {
        $apiKey = trim((string) Config::get('indexnow.api_key'));
        $context = $this->sanitizeContext($context, $apiKey);
        $message = $this->sanitizeString((string) $message, $apiKey);

        $suffix = '';
        if ($context) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $suffix = ' ' . $json;
            }
        }
        Log::addEntry('[IndexNow][' . $level . '] ' . $message . $suffix);
    }

    protected function sanitizeContext(array $context, $apiKey)
    {
        foreach ($context as $name => $value) {
            if (in_array((string) $name, ['key', 'api_key', 'apiKey'], true)) {
                unset($context[$name]);
                continue;
            }
            if (is_array($value)) {
                $context[$name] = $this->sanitizeContext($value, $apiKey);
            } elseif (is_string($value)) {
                $context[$name] = $this->sanitizeString($value, $apiKey);
            }
        }
        return $context;
    }

    protected function sanitizeString($value, $apiKey)
    {
        $value = (string) $value;
        if ($apiKey !== '') {
            $value = str_replace($apiKey, '[REDACTED_INDEXNOW_KEY]', $value);
        }

        // Also protect custom endpoint URLs that happen to contain a key
        // query parameter, even if it differs from the currently saved key.
        $value = preg_replace(
            '/([?&](?:key|api_key|apikey)=)[^&#\\s]+/i',
            '$1[REDACTED_INDEXNOW_KEY]',
            $value
        );
        return $value;
    }
}
