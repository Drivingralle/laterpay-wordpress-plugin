<?php

class LaterPayClient_Signing {
    protected static $hashAlgo = 'sha224';

    protected static function timeIndependentHmacCompare( $a, $b ) {
        if ( strlen($a) != strlen($b) ) {
            return false;
        }

        return $a == $b;
    }

    protected static function createHmac( $secret, $parts ) {
        if ( is_array($parts) ) {
            $data = join('', $parts);
        } else {
            $data = (string)$parts;
        }

        $crypt = new Crypt_Hash(self::$hashAlgo);
        $crypt->setKey($secret);
        $hash = bin2hex($crypt->hash($data));

        Logger::debug('LaterPayClient_Signing::createHmac', array($hash));

        return $hash;
    }

    public static function verify( $signature, $secret, $params, $url, $method ) {

        if ( is_array($signature) ) {
            $signature = $signature[0];
        }

        $mac = self::sign($secret, $params, $url, $method);

        return self::timeIndependentHmacCompare($signature, $mac);
    }

    /**
     * Request parameter dictionaries are handled in different ways in different libraries,
     * this function is required to ensure we always have something of the format
     * { key: [ value1, value2, ... ] }
     *
     * @param array $params
     *
     * @return array
     */
    protected static function normaliseParamStructure( $params ) {
        $out = array();

        // this is tricky - either we have (a, b), (a, c) or we have (a, (b, c))
        foreach ( $params as $param_name => $param_value ) {
            if ( is_array($param_value) ) {
                // this is (a, (b, c))
                $out[$param_name] = $param_value;
            } else {
                // this is (a, b), (a, c)
                if ( !in_array($param_name, $out) ) {
                    $out[$param_name] = array();
                }
                $out[$param_name][] = $param_value;
            }
        }

        Logger::debug('LaterPayClient_Signing::normaliseParamStructure', array($params, $out));

        return $out;
    }

    /**
     * Create base message
     *
     * @param array  $params mapping of all parameters that should be signed
     * @param string $url    full URL of the target endpoint, no URL parameters
     * @param string $method
     *
     * @return string
     */
    protected static function createBaseMessage( $params, $url, $method = Request::POST ) {
        $msg = '{method}&{url}&{params}';
        $method = strtoupper($method);

        $data   = array();
        $url    = rawurlencode(utf8_encode($url));
        $params = self::normaliseParamStructure($params);

        $keys = array_keys($params);
        sort($keys);
        foreach ( $keys as $key ) {
            $value  = $params[$key];
            $key    = rawurlencode(utf8_encode($key));

            if ( !is_array($value) ) {
                $value = array($value);
            }

            $encoded_value = '';
            sort($value);
            foreach ( $value as $v ) {
                if ( mb_detect_encoding($v, 'UTF-8') !== 'UTF-8' ) {
                    $encoded_value = rawurlencode(utf8_encode($v));
                } else {
                    $encoded_value = rawurlencode($v);
                }
                $data[] = $key . '=' . $encoded_value;
            }
        }

        $param_str = rawurlencode(join('&', $data));
        $result = str_replace(array('{method}', '{url}', '{params}'), array($method, $url, $param_str), $msg);

        Logger::debug('LaterPayClient_Signing::createBaseMessage', array($result));

        return $result;
    }

    /**
     * Create signature for given `params`, `url` and HTTP method
     *
     * @param string $secret secret used to create signature
     * @param array  $params mapping of all parameters that should be signed
     * @param string $url    full URL of the target endpoint, no URL parameters
     * @param string $method
     *
     * @return string
     */
    protected static function sign( $secret, $params, $url, $method = Request::POST ) {

        Logger::debug('LaterPayClient_Signing::sign', array($secret, $params, $url, $method));

        $secret = utf8_encode($secret);

        if ( isset($params['hmac']) ) {
            unset($params['hmac']);
        }

        if ( isset($params['gettoken']) ) {
            unset($params['gettoken']);
        }

        $aux = explode('?', $url);
        $url = $aux[0];
        $msg = self::createBaseMessage($params, $url, $method);
        $mac = self::createHmac($secret, $msg);

        return $mac;
    }

    /**
     * Preprocess parameters
     *
     * @param string $secret
     * @param array  $params array params
     * @param string $url
     * @param string $method HTTP method
     *
     * @return string query params
     */
    public static function signAndEncode( $secret, $params, $url, $method = Request::GET ) {
        if ( !isset($params['ts']) ) {
            $params['ts'] = (string)time();
        }

        if ( isset($params['hmac']) ) {
            unset($params['hmac']);
        }

        // get the keys in alphabetical order
        $keys = array_keys($params);
        sort($keys);
        $query_pairs = array();
        foreach ( $keys as $key ) {
            $aux = $params[$key];
            $key = utf8_encode($key);

            if ( !is_array($aux) ) {
                $aux = array($aux);
            }
            sort($aux);
            foreach ( $aux as $value ) {
                if ( mb_detect_encoding($value, 'UTF-8') !== 'UTF-8' ) {
                    $value = rawurlencode(utf8_encode($value));
                }
                $query_pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }

        // build the querystring
        $encoded = join('&', $query_pairs);

        // hash the querystring data
        $hmac = self::sign($secret, $params, $url, $method);

        Logger::debug('LaterPayClient_Signing::sign', array('encoded' => $encoded));

        return $encoded . '&hmac=' . $hmac;
    }

}
