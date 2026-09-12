<?php

class FetchIt
{
    public $version = '1.1.4';
    /** @var modX $modx */
    public $modx;
    /** @var array $config */
    public $config;
    /** @var string|null Rotated token to return after AJAX process */
    protected $pendingNewToken = null;


    /**
     * @param modX $modx
     * @param array $config
     */
    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;

        $corePath = $this->modx->getOption('fetchit.core_path', $config,
            $this->modx->getOption('core_path') . 'components/fetchit/');
        $assetsPath = $this->modx->getOption('fetchit.assets_path', $config,
            $this->modx->getOption('assets_path') . 'components/fetchit/');
        $assetsUrl = $this->modx->getOption('fetchit.assets_url', $config,
            $this->modx->getOption('assets_url') . 'components/fetchit/');
        $frontend_js = $this->modx->getOption('fetchit.frontend.js', null,
            '[[+assetsUrl]]js/default.js');
        $default_notifier = (bool)$this->modx->getOption('fetchit.frontend.default.notifier', null,
            true, false);

        $this->modx->lexicon->load('fetchit:default');

        $this->config = array_merge(array(
            'assetsUrl' => $assetsUrl,
            'actionUrl' => $assetsUrl . 'action.php',

            'json_response' => true,

            'corePath' => $corePath,
            'assetsPath' => $assetsPath,

            'frontend_js' => $frontend_js,

            'default_notifier' => $default_notifier,
        ), $config);
    }


    /**
     * Whether connector submit-token protection is enabled.
     *
     * @return bool
     */
    public function isProtectEnabled()
    {
        return (bool)$this->modx->getOption('fetchit.protect.enabled', null, true);
    }


    /**
     * @return string
     */
    protected function generateActionToken()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            return md5(uniqid((string)mt_rand(), true));
        }
    }


    /**
     * Ensure an action has a submit token in session/cache storage; return it.
     *
     * @param string $action
     *
     * @return string
     */
    public function ensureActionToken($action)
    {
        $stored = $this->loadActionProperties($action);
        if (is_array($stored) && !empty($stored['_token']) && is_string($stored['_token'])) {
            return $stored['_token'];
        }

        $token = $this->generateActionToken();
        if (!is_array($stored)) {
            $stored = array();
        }
        $stored['_token'] = $token;
        $this->writeActionProperties($action, $stored);

        return $token;
    }


    /**
     * Write action properties to session and/or cache (always mirror to cache when protecting).
     *
     * @param string $action
     * @param array $scriptProperties
     */
    protected function writeActionProperties($action, array $scriptProperties)
    {
        $forCache = $scriptProperties;
        unset($forCache['_token']);

        if (!empty(session_id())) {
            if (!isset($_SESSION['FetchIt'])) {
                $_SESSION['FetchIt'] = array();
            }
            $_SESSION['FetchIt'][$action] = $scriptProperties;
        }

        // Never put one-time tokens into the shared cache key.
        $this->modx->cacheManager->set(
            $this->getActionPropertiesCacheKey($action),
            $forCache,
            3600
        );
    }


    /**
     * Independent registration of JavaScripts
     */
    public function loadScript($action)
    {
        if (!empty(session_id())) {
            $_SESSION['fetchit_called'] = true;
        }

        $configPayload = [
            'action' => $action,
            'assetsUrl' => $this->config['assetsUrl'],
            'actionUrl' => str_replace('[[+assetsUrl]]', $this->config['assetsUrl'], $this->config['actionUrl']),
            'inputInvalidClass' => trim(preg_replace('/\s+/', ' ', $this->modx->getOption('fetchit.frontend.input.invalid.class'))),
            'customInvalidClass' => trim(preg_replace('/\s+/', ' ', $this->modx->getOption('fetchit.frontend.custom.invalid.class'))),
            'clearFieldsOnSuccess' => (bool)$this->modx->getOption('clearFieldsOnSuccess', $this->config, 1, false),
            'defaultNotifier' => $this->config['default_notifier'],
            'pageId' => !empty($this->modx->resource)
                ? $this->modx->resource->get('id')
                : 0,
        ];

        if ($this->isProtectEnabled()) {
            $configPayload['token'] = $this->ensureActionToken($action);
        }

        $config = $this->modx->toJSON($configPayload);
        $js_classname = trim($this->modx->getOption('fetchit.frontend.js.classname', null, 'FetchIt', true));
        $this->modx->regClientHTMLBlock("<script>window.addEventListener('DOMContentLoaded', () => {$js_classname}.create($config));</script>");
    }


    /**
     * Registers the main script first
     */

    public function registerScript()
    {
        if (empty($_SESSION['fetchit_called'])) {
            return;
        }

        $js = trim($this->config['frontend_js']);
        if (!preg_match('/\.js/i', $js)) {
            return;
        }

        $assets = ['<script src="' . str_replace('[[+assetsUrl]]', $this->config['assetsUrl'], $js) . '?v=' . $this->version . '" defer></script>'];

        if ($this->config['default_notifier']) {
            array_unshift($assets,
                '<link rel="stylesheet" href="' . $this->config['assetsUrl'] . 'lib/notyf.min.css?v=' . $this->version . '" />',
                '<script src="' . $this->config['assetsUrl'] . 'lib/notyf.min.js?v=' . $this->version . '" defer></script>'
            );
        }

        $assets = join(PHP_EOL, $assets);
        $output = &$this->modx->resource->_output;

        if (strpos($output, '</head>') === false) {
            return;
        }

        if (preg_match('#(?:<head>[\s\S]*?)(\s*?<script[\s\S]*?((</script>)|(/>)))(?:[\s\S]*?</head>)#i', $output, $matches)) {
            $script = $matches[1];
            $script = preg_replace('/<script[\s\S]*<\/script>/', $assets, $script);
            $output = preg_replace('#(<head>[\s\S]*?)(\s*?<script[\s\S]*?</script>)([\s\S]*?</head>)#', "$1$assets$2$3", $output, 1);
        } else {
            $output = preg_replace("/(<\/head>)/i", $assets . "\n\\1", $output, 1);
        }

        unset($_SESSION['fetchit_called']);
    }


    /**
     * @param string $action
     *
     * @return string
     */
    protected function getActionPropertiesCacheKey($action)
    {
        return 'fetchit/props_' . $action;
    }


    /**
     * Persist snippet properties for AJAX process(); strip non-serializable values (#17).
     *
     * @param string $action
     * @param array $scriptProperties
     */
    public function storeActionProperties($action, array $scriptProperties)
    {
        foreach ($scriptProperties as $key => $value) {
            if (is_object($value) || is_resource($value)) {
                unset($scriptProperties[$key]);
            }
        }

        if ($this->isProtectEnabled()) {
            $existing = $this->loadActionProperties($action);
            if (is_array($existing) && !empty($existing['_token']) && is_string($existing['_token'])) {
                $scriptProperties['_token'] = $existing['_token'];
            } else {
                $scriptProperties['_token'] = $this->generateActionToken();
            }
        }

        $this->writeActionProperties($action, $scriptProperties);
    }


    /**
     * Stored action properties, or null if missing / invalid.
     *
     * @param string $action
     *
     * @return array|null
     */
    public function loadActionProperties($action)
    {
        if (!empty(session_id()) && isset($_SESSION['FetchIt'][$action])) {
            $stored = $_SESSION['FetchIt'][$action];
        } else {
            $stored = $this->modx->cacheManager->get($this->getActionPropertiesCacheKey($action));
        }

        if (empty($stored) || !is_array($stored)) {
            return null;
        }

        return $stored;
    }


    /**
     * Loads snippet for form processing
     *
     * @param $action
     * @param array $fields
     *
     * @return array|string
     */
    public function process($action, array $fields = array())
    {
        $stored = $this->loadActionProperties($action);
        if ($stored === null) {
            return $this->error('fetchit_err_action_nf');
        }

        $isAjax = !empty($_SERVER['HTTP_X_FETCHIT_ACTION']);
        $this->pendingNewToken = null;

        if ($isAjax && $this->isProtectEnabled()) {
            $tokenCheck = $this->validateAndRotateActionToken($action, $stored);
            if ($tokenCheck !== true) {
                return $tokenCheck;
            }
        }

        // Do not set FetchIt=>$this here (PDO in session, #17).
        // Custom snippets: $modx->getService('fetchit', 'FetchIt', MODX_CORE_PATH . 'components/fetchit/model/', []).
        unset($stored['_token']);
        $scriptProperties = array_merge($stored, array(
            'fields' => $fields,
        ));

        if ($isAjax) {
            $before = $this->runBeforeProcessEvent($action, $fields, $scriptProperties);
            if ($before !== true) {
                return $this->attachNewTokenToResponse($before);
            }
        }

        $name = $scriptProperties['snippet'];
        $set = '';
        if (strpos($name, '@') !== false) {
            list($name, $set) = explode('@', $name);
        }

        /** @var modSnippet $snippet */
        if ($snippet = $this->modx->getObject('modSnippet', array('name' => $name))) {
            $properties = $snippet->getProperties();
            $property_set = !empty($set)
                ? $snippet->getPropertySet($set)
                : array();

            $scriptProperties = array_merge($properties, $property_set, $scriptProperties);
            $snippet->_cacheable = false;
            $snippet->_processed = false;

            $response = $snippet->process($scriptProperties);
            if (strtolower($snippet->name) == 'formit') {
                $response = $this->handleFormIt($scriptProperties);
            }

            return $this->attachNewTokenToResponse($response);
        } else {
            return $this->attachNewTokenToResponse(
                $this->error('fetchit_err_snippet_nf', array(), array('name' => $name))
            );
        }
    }


    /**
     * Validate X-FetchIt-Token, rotate stored token on success.
     *
     * @param string $action
     * @param array $stored
     *
     * @return true|string
     */
    protected function validateAndRotateActionToken($action, array &$stored)
    {
        $expected = isset($stored['_token']) ? (string)$stored['_token'] : '';
        $provided = isset($_SERVER['HTTP_X_FETCHIT_TOKEN'])
            ? (string)$_SERVER['HTTP_X_FETCHIT_TOKEN']
            : '';

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return $this->error('fetchit_err_token');
        }

        $newToken = $this->generateActionToken();
        $stored['_token'] = $newToken;
        $this->writeActionProperties($action, $stored);
        $this->pendingNewToken = $newToken;

        return true;
    }


    /**
     * Allow plugins (IskWaf, custom) to abort AJAX processing without patching action.php.
     *
     * @param string $action
     * @param array $fields
     * @param array $scriptProperties
     *
     * @return true|string
     */
    protected function runBeforeProcessEvent($action, array &$fields, array $scriptProperties)
    {
        $outputs = $this->modx->invokeEvent('OnFetchItBeforeProcess', array(
            'action' => $action,
            'fields' => &$fields,
            'scriptProperties' => $scriptProperties,
            'FetchIt' => $this,
        ));

        $values = array();
        if (is_array($outputs)) {
            $values = array_merge($values, $outputs);
        }
        if (!empty($this->modx->event->returnedValues) && is_array($this->modx->event->returnedValues)) {
            $values = array_merge($values, $this->modx->event->returnedValues);
        }

        foreach ($values as $value) {
            if ($value === false) {
                return $this->error('fetchit_err_before_process');
            }
            if (is_string($value) && $value !== '') {
                // Plugin may already return JSON via $modx->event->output().
                $decoded = json_decode($value, true);
                if (is_array($decoded) && array_key_exists('success', $decoded)) {
                    return $value;
                }

                return $this->error($value);
            }
            if (is_array($value) && array_key_exists('success', $value) && !$value['success']) {
                $message = !empty($value['message']) ? $value['message'] : 'fetchit_err_before_process';
                $data = !empty($value['data']) && is_array($value['data']) ? $value['data'] : array();

                return $this->error($message, $data);
            }
        }

        return true;
    }


    /**
     * @param array|string $response
     *
     * @return array|string
     */
    protected function attachNewTokenToResponse($response)
    {
        if ($this->pendingNewToken === null || $this->pendingNewToken === '') {
            return $response;
        }

        $token = $this->pendingNewToken;
        $this->pendingNewToken = null;

        if (is_array($response)) {
            if (!isset($response['data']) || !is_array($response['data'])) {
                $response['data'] = array();
            }
            $response['data']['newToken'] = $token;

            return $response;
        }

        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                if (!isset($decoded['data']) || !is_array($decoded['data'])) {
                    $decoded['data'] = array();
                }
                $decoded['data']['newToken'] = $token;

                return $this->config['json_response']
                    ? $this->modx->toJSON($decoded)
                    : $decoded;
            }
        }

        return $response;
    }


    /**
     * @param string $key
     *
     * @return string
     */
    protected function getSanitizedPlaceholder($key)
    {
        if (!isset($this->modx->placeholders[$key])) {
            return '';
        }

        $value = html_entity_decode((string)$this->modx->placeholders[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(strip_tags($value));
    }


    /**
     * @param string $plPrefix
     * @param string $field
     *
     * @return string
     */
    protected function getFieldError($plPrefix, $field)
    {
        return $this->getSanitizedPlaceholder($plPrefix . 'error.' . $field);
    }


    /**
     * Method for obtaining data from FormIt
     *
     * @param array $scriptProperties
     *
     * @return array|string
     */
    public function handleFormIt(array $scriptProperties = array())
    {
        $plPrefix = isset($scriptProperties['placeholderPrefix'])
            ? $scriptProperties['placeholderPrefix']
            : 'fi.';

        $errors = array();
        foreach ($scriptProperties['fields'] as $k => $v) {
            $error = $this->getFieldError($plPrefix, $k);
            if ($error !== '') {
                $errors[$k] = $error;
            }
        }

        foreach (array('recaptcha', 'recaptchav2_error', 'recaptchav3_error') as $recaptchaField) {
            $error = $this->getFieldError($plPrefix, $recaptchaField);
            if ($error !== '') {
                $errors['recaptcha'] = $error;
                break;
            }
        }

        if (!empty($errors)) {
            $message = $this->getSanitizedPlaceholder($plPrefix . 'validation_error_message');
            if ($message === '') {
                $message = 'fetchit_err_has_errors';
            }
            $status = 'error';
        } else {
            $message = !empty($scriptProperties['successMessage'])
                ? $scriptProperties['successMessage']
                : (isset($this->modx->placeholders[$plPrefix . 'successMessage'])
                    ? $this->modx->placeholders[$plPrefix . 'successMessage']
                    : 'fetchit_success_submit');
            $status = 'success';
        }

        return $this->$status($message, $errors);
    }


    /**
     * This method returns an error of the order
     *
     * @param string $message A lexicon key for error message
     * @param array $data .Additional data, for example cart status
     * @param array $placeholders Array with placeholders for lexicon entry
     *
     * @return array|string $response
     */
    public function error($message = '', $data = array(), $placeholders = array())
    {
        $response = array(
            'success' => false,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        );

        return $this->config['json_response']
            ? $this->modx->toJSON($response)
            : $response;
    }


    /**
     * This method returns an success of the order
     *
     * @param string $message A lexicon key for success message
     * @param array $data .Additional data, for example cart status
     * @param array $placeholders Array with placeholders for lexicon entry
     *
     * @return array|string $response
     */
    public function success($message = '', $data = array(), $placeholders = array())
    {
        $response = array(
            'success' => true,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        );

        return $this->config['json_response']
            ? $this->modx->toJSON($response)
            : $response;
    }
}
