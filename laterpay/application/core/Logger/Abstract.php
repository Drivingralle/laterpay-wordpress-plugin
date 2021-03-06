<?php

abstract class Logger_Abstract {
    protected $level = Logger::DEBUG;

    public function __construct( $level = Logger::DEBUG ) {
        $this->level = $level;
    }

    public function handle( array $record ) {
        if ( $record['level'] < $this->level ) {
            return false;
        }

        $record['formatted'] = $this->getFormated($record);

        $this->write($record);

        return true;
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param array   $record
     * @return void
     */
    abstract protected function write( array $record );

    protected function getFormated( array $record ) {
        $output = "%datetime%:%pid%.%channel%.%level_name%: %message% %context%\n";
        foreach ( $record as $var => $val ) {
            $output = str_replace('%'.$var.'%', $this->convertToString($val), $output);
        }

        return $output;
    }

    /**
     * Closes the handler.
     *
     * This will be called automatically when the object is destroyed
     */
    public function close() {

    }

    public function __destruct() {
        try {
            $this->close();
        } catch ( Exception $e ) {
            // do nothing
        }
    }

    protected function convertToString( $data ) {
        if ( null === $data || is_scalar($data) ) {
            return (string)$data;
        }

        if ( version_compare(PHP_VERSION, '5.4.0', '>=') && defined('JSON_UNESCAPED_SLASHES') && defined('JSON_UNESCAPED_UNICODE') ) {
            return json_encode($this->normalize($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return str_replace('\\/', '/', Zend_Json::encode( $this->normalize($data)));
    }

    protected function normalize( $data ) {
        if ( is_bool($data) || is_null($data) ) {
            return var_export($data, true);
        }

        if ( null === $data || is_scalar($data) ) {
            return $data;
        }

        if ( is_array($data) || $data instanceof Traversable ) {
            $normalized = array();

            foreach ( $data as $key => $value ) {
                $normalized[$key] = $this->normalize($value);
            }

            return $normalized;
        }

        if ( $data instanceof DateTime ) {
            return $data->format('Y-m-d H:i:s.u');
        }

        if ( is_object($data) ) {
            return sprintf("[object] (%s: %s)", get_class($data), Zend_Json::encode($data));
        }

        if ( is_resource($data) ) {
            return '[resource]';
        }

        return '[unknown('.gettype($data).')]';
    }

}
