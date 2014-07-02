<?php
class AnsibleModule
{
    protected static $typeClassMap = array(
        'default'   => 'AnsibleModuleArgument',
        'directory' => 'AnsibleModuleDirectoryArgument',
        'float'     => 'AnsibleModuleNumberArgument',
        'int'       => 'AnsibleModuleNumberArgument',
        'integer'   => 'AnsibleModuleNumberArgument',
        'list'      => 'AnsibleModuleListArgument',
        'number'    => 'AnsibleModuleNumberArgument',
        'uri'       => 'AnsibleModuleUriArgument',
        'url'       => 'AnsibleModuleUriArgument',
    );

    public $params = array();

    /**
     * @param array $argumentSpec Argument specification. Array of arrays (dictionaries).
     */
    public function __construct(array $argumentSpec)
    {
        global $argv;

        $yesNo = array('yes', 'no', 'true', 'false');

        foreach ($argumentSpec as $key => $spec) {
            if (!is_array($spec)) {
                $this->failJson(array('msg' => sprintf('Argument keyword "%s" is not an array', $key)));
            }
        }

        $argFile = $argv[count($argv) - 1];
        $args = preg_split('/\s+/', file_get_contents($argFile), null, PREG_SPLIT_NO_EMPTY);

        foreach ($args as $i => $arg) {
            @list($key, $val) = preg_split('/=/', $arg, 2);

            if (!$key || !isset($key) || !isset($val)) {
                $this->failJson(array('msg' => sprintf('Argument at index %d is invalid', $i)));
            }

            if (!isset($argumentSpec[$key])) {
                $this->failJson(sprintf('Argument "%s" is invalid', $key));
            }

            $spec = $argumentSpec[$key];
            $typeClass = static::$typeClassMap['default'];
            $isGenericType = true;

            if (isset($spec['type'])) {
                if (!isset(static::$typeClassMap[$spec['type']])) {
                    $this->failJson(array('msg' => sprintf('Argument type "%s" is not valid')));
                }

                $typeClass = static::$typeClassMap[$spec['type']];
                $isGenericType = false;

                if (!class_exists($typeClass)) {
                    $this->failJson(array('msg' => sprintf('Class "%s" for type "%s" does not exist', $typeClass, $spec['type'])));
                }
            }

            if (in_array(strtolower($val), $yesNo) && $isGenericType) {
                $val = (bool) $val;
            }

            $required = isset($spec['required']) ? (bool) $spec['required'] : false;
            $typedArg = new $typeClass($required);

            $typedArg->setValue($val);

            if (!$typedArg->isValid()) {
                $this->failJson(array('msg' => sprintf('Argument %s is invalid. %s', $arg, $typedArg->getValidationMessage())));
            }

            $this->params[$key] = $typedArg->getValue();
        }

        foreach ($argumentSpec as $key => $spec) {
            if (isset($spec['default']) && !isset($this->params[$key])) {
                $this->params[$key] = $spec['default'];
            }
        }

        //$this->checkRequiredTogether();
        //$this->checkRequiredOneOf();
    }

    /**
     * @param boolean $changed Changed state.
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
     * @param array $args
     */
    public function failJson(array $args = array())
    {
        $args['failed'] = true;
        $json = json_encode($args);

        $this->checkJsonState();

        print $json;
        exit(1);
    }

    protected function checkJsonState()
    {
        if (json_last_error() !== JSON_ERROR_NONE) {
            print json_encode(sprintf('%s:%s: Failed to encode JSON', __CLASS__, __FUNCTION__));
            exit(1);
        }
    }
}
