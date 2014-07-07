<?php
namespace AnsiblePhp;

/**
 * Exception that handles arguments like `sprintf()`.
 *
 * @see sprintf()
 */
abstract class AbstractStringFormatException extends \Exception
{
    /**
     * Constructor. Takes a format string and a variable amount of arguments
     *   like `sprintf()`.
     *
     * @param string     $message  Format string.
     * @param integer    $code     Ignored.
     * @param \Exception $previous Ignored.
     *
     * @see sprintf()
     * @see #setCode()
     * @see #setPrevious()
     */
    public function __construct($message, $code = 0, $previous = null)
    {
        $args = func_get_args();
        $format = array_shift($args);

        if (count($args) === 0) {
            $this->message = $format;

            return;
        }

        $this->message = vsprintf($format, $args);
    }

    /**
     * Because the code argument is ignored in the constructor, this helper
     *   sets the exception code if necessary.
     *
     * @param integer $code Code to set.
     */
    public function setCode($code)
    {
        $this->code = (int) $code;
    }
}
