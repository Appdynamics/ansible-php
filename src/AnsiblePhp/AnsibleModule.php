<?php
namespace AnsiblePhp;

/**
 * Ansible module class, based in part on Ansible's own Python module.
 */
class AnsibleModule
{
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
     * Parameters. This is populated after all arguments are validated.
     *
     * @var array
     */
    public $params = array();

    /**
     * Constructor.
     *
     * @param array $argumentSpec Argument specification. Array of arrays (dictionaries).
     */
    public function __construct(array $argumentSpec)
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
        $values = preg_split('/([a-z_\-]+)=/', $argFile, null, PREG_SPLIT_NO_EMPTY);

        if (count($args) !== count($values)) {
            throw new ValidationException('Argument count did not match value count');
        }

        foreach ($args as $i => $key) {
            $val = substr($values[$i], 0, -1);

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

                if (in_array($val, $yesNo, true)) {
                    if (in_array($val, $yes, true)) {
                        $val = true;
                    }
                    else {
                        $val = false;
                    }
                }
                else {
                    throw new ValidationException('A boolean value should be one of the following values: %s. Got "%s"', join(', ', $yesNo), (string) $val);
                }
            }

            if ($type === 'directory') {
                $val = trim($val);
                if (!is_dir($val)) {
                    throw new ValidationException('Directory %s does not exist (key: %s)', $val, $key);
                }
            }

            if ($type === 'float') {
                $val = (float) trim($val);
            }

            if ($type === 'int' || $type === 'integer') {
                $val = trim($val);
                if (!is_numeric($val)) {
                    throw new ValidationException('Expected a numberic value to convert to integer');
                }

                $val = (int) $val;
            }

            if ($type === 'list') {
                $val = trim($val);
                $val = preg_split('/,/', $val, null, PREG_SPLIT_NO_EMPTY);
                if (!$val) {
                    throw new ValidationException('Expected list but expanding list argument failed');
                }
            }

            if ($type === 'number') {
                $val = trim($val);
                if (!is_numeric($val)) {
                    throw new ValidationException('Expected numeric argument for key %s', $key);
                }
            }

            if ($type === 'uri' || $type === 'url') {
                $val = trim($val);
                if (!parse_url($val)) {
                    throw new ValidationException('Expected valid URI for key %s', $key);
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
                throw new ValidationException('Argument %s is required', $key);
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
        $json = json_encode($args);

        $this->checkJsonState();

        print $json;
        exit(0);
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
        $json = json_encode($args);

        $this->checkJsonState();

        print $json;
        exit(1);
    }

    /**
     * Used to check JSON state consistantly. Exits with 1 if JSON fails to be
     *   encoded.
     */
    protected function checkJsonState()
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            print '{"failed":true,"msg":"Failed to encode JSON"}';
            exit(1);
        }
    }
}
