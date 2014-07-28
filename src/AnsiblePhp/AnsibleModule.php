<?php
namespace AnsiblePhp;

use Hampel\Json\Json;

/**
 * Ansible module class, based in part on Ansible's own Python module.
 */
class AnsibleModule
{
    /**
     * Parameters. This is populated after all arguments are validated.
     *
     * @var array
     */
    public $params = array();

    /**
     * Valid argument types that can be used in the spec.
     *
     * @var array
     */
    protected static $validArgumentTypes = array(
        'bool',
        'boolean',
        'directory',
        'float',
        'int',
        'integer',
        'list',
        'number',
        'string',
        'uri',
        'url',
    );

    /**
     * Constructor.
     *
     * @param array   $argumentSpec Argument specification. Array of arrays (dictionaries).
     * @param boolean $trimStrings  If all string arguments should be trimmed.
     *
     * @todo Implement choices.
     */
    public function __construct(array $argumentSpec, $trimStrings = true)
    {
        global $argv;

        $yesNo = array('yes', 'no', 'true', 'false');
        $yes = array('yes', 'true');

        $argumentSpec['ansible_php'] = array('type' => 'directory', 'required' => true);

        foreach ($argumentSpec as $key => $spec) {
            if (!is_array($spec)) {
                throw new InvalidArgumentException('Argument keyword "%s" is not an array', $key);
            }
        }

        $argFile = $argv[count($argv) - 1];
        $argFile = file_get_contents($argFile);
        $args = array();
        preg_match_all('/([a-z_\-]+)=/', $argFile, $args);
        $args = $args[1];
        $values = preg_split('/[a-z_\-]+=/', $argFile, null, PREG_SPLIT_NO_EMPTY);

        if (count($args) !== count($values)) {
            throw new ValidationException('Argument count did not match value count');
        }

        foreach ($args as $i => $key) {
            $val = $values[$i];

            // Validate arguments
            if (!isset($argumentSpec[$key])) {
                throw new InvalidArgumentException('Argument "%s" is invalid', $key);
            }

            // Validate and 'casting' of types
            $spec = $argumentSpec[$key];
            $type = isset($spec['type']) ? $spec['type'] : 'string';

            if (!in_array($type, static::$validArgumentTypes)) {
                throw new TypeException('%s is not a valid argument type', $spec['type']);
            }

            if ($type === 'bool' || $type === 'boolean') {
                $strVal = strtolower(trim($val));

                if (in_array($strVal, $yesNo, true)) {
                    if (in_array($strVal, $yes, true)) {
                        $val = true;
                    }
                    else {
                        $val = false;
                    }
                }
                else {
                    throw new ValidationException('A boolean value should be one of the following values: %s. Got "%s"', join(', ', $yesNo), (string) $strVal);
                }
            }

            if ($type === 'directory') {
                $val = trim($val);
                if (!is_dir($val)) {
                    throw new ValidationException('Directory "%s" does not exist (key: "%s")', $val, $key);
                }
            }

            if ($type === 'float') {
                $val = trim($val);
                if (!is_numeric($val)) {
                    throw new ValidationException('Expected a numberic value to convert to float (key: "%s")', $key);
                }

                $val = (float) $val;
            }

            if ($type === 'int' || $type === 'integer') {
                $val = trim($val);
                if (!is_numeric($val)) {
                    throw new ValidationException('Expected a numberic value to convert to integer (key: "%s")', $key);
                }

                $val = (int) $val;
            }

            if ($type === 'list') {
                $val = trim($val);
                $val = preg_split('/,/', $val, null, PREG_SPLIT_NO_EMPTY);
                if (!$val) {
                    throw new ValidationException('Expected list but expanding list argument failed (key: "%s")', $key);
                }
            }

            if ($type === 'number') {
                $val = trim($val);
                if (!is_numeric($val)) {
                    throw new ValidationException('Expected numeric argument for key "%s"', $key);
                }
            }

            if ($type === 'uri' || $type === 'url') {
                $val = trim($val);
                $parsed = parse_url($val);

                // Even for junk (bytes), at least key path may be returned
                if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
                    throw new ValidationException('Expected valid URI for key "%s"', $key);
                }
            }

            if ($type === 'string') {
                if ($trimStrings) {
                    $val = trim($val);
                }
                else {
                    $val = substr($val, 0, -1);
                }
            }

            $this->params[$key] = $val;
        }

        foreach ($argumentSpec as $key => $spec) {
            if (isset($spec['default']) && !isset($this->params[$key])) {
                $this->params[$key] = $spec['default'];
            }
            if (isset($spec['type']) && $spec['type'] === 'list' && !isset($this->params[$key])) {
                $this->params[$key] = array();
            }
            else if (!isset($this->params[$key])) {
                $this->params[$key] = null; // Make the key exist, but isset() still fail
            }
            // TODO Move to top
            if (isset($spec['required']) && $spec['required'] && !isset($this->params[$key])) {
                throw new ValidationException('Argument "%s" is required', $key);
            }
        }

        //$this->checkRequiredTogether();
        //$this->checkRequiredOneOf();
    }

    /**
     * Exit with JSON string.
     *
     * @param array $args Arguments for the JSON array.
     */
    public function exitJson(array $args = array())
    {
        $args['changed'] = isset($args['changed']) ? (bool) $args['changed'] : false;
        $json = Json::encode($args);

        print $json;
        $this->terminate();
    }

    /**
     * Exit with status 1 and failed field set to `true`.
     *
     * @param array $args Arguments for the JSON array. The failed key will
     *   always be overriden.
     */
    public function failJson(array $args = array())
    {
        $args['failed'] = true;
        $json = Json::encode($args);

        print $json;
        $this->terminate(1);
    }

    /**
     * Helper to decode JSON. Checks for errors.
     *
     * @param string  $str   String to decode.
     * @param boolean $assoc Decode to associative array.
     *
     * @return mixed PHP value.
     */
    public function decodeJson($str, $assoc = true)
    {
        return Json::decode($str, $assoc);
    }

    /**
     * Termination handler (so this class can be mocked).
     *
     * @param integer $code Exit status.
     */
    protected function terminate($code = 0)
    {
        exit($code);
    }
}
