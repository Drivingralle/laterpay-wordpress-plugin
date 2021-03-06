<?php

class StatisticsHelper {

    public static $wpdb = '';
    protected static $stat = array();
    protected static $options = array(
        'secret'            => LATERPAY_SALT,
        'session_duration'  => 2678400,  // one month
    );

    protected static function getUniqueId() {
        return str_replace('.', '', uniqid(rand(0, 2147483647), true));
    }

    /**
     * Try to find the user's REAL IP address
     */
    protected static function getIP2LongRemoteIP() {
        $long_ip = array( 0, 0 );

        if ( !function_exists('inet_pton') ) {

            function inet_pton( $ip ) {
                // IPv4
                if ( strpos($ip, '.') !== false ) {
                    if ( strpos($ip, ':') === false )
                        $ip = pack('N', ip2long($ip));
                    else {
                        $ip = explode(':', $ip);
                        $ip = pack('N', ip2long($ip[count($ip) - 1]));
                    }
                }
                // IPv6
                elseif ( strpos($ip, ':') !== false ) {
                    $ip         = explode(':', $ip);
                    $parts      = 8 - count($ip);
                    $res        = '';
                    $replaced   = 0;
                    foreach ( $ip as $seg ) {
                        if ( $seg != '' )
                            $res .= str_pad($seg, 4, '0', STR_PAD_LEFT);
                        elseif ( $replaced == 0 ) {
                            for ( $i = 0; $i <= $parts; $i++ ) {
                                $res .= '0000';
                            }
                            $replaced = 1;
                        } elseif ( $replaced == 1 )
                            $res .= '0000';
                    }
                    $ip = pack('H' . strlen($res), $res);
                }

                return $ip;
            }

        }

        if ( isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) !== false ) {
            $long_ip[0] = inet_pton($_SERVER['REMOTE_ADDR']);
        }

        if ( isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) !== false ) {
            $long_ip[1] = inet_pton($_SERVER['HTTP_CLIENT_IP']);
        }

        if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
            foreach ( explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $a_ip ) {
                if ( filter_var($a_ip, FILTER_VALIDATE_IP) !== false ) {
                    $long_ip[1] = inet_pton($a_ip);

                    return $long_ip;
                }
            }
        }

        if ( isset($_SERVER['HTTP_FORWARDED']) && filter_var($_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP) !== false ) {
            $long_ip[1] = inet_pton($_SERVER['HTTP_FORWARDED']);
        }

        if ( isset($_SERVER['HTTP_X_FORWARDED']) && filter_var($_SERVER['HTTP_X_FORWARDED'], FILTER_VALIDATE_IP) !== false ) {
            $long_ip[1] = inet_pton($_SERVER['HTTP_X_FORWARDED']);
        }

        return $long_ip;
    }

    /**
     * Core tracking functionality
     */
    public static function track( $post_id = '' ) {
        if ( empty($post_id) ) {
            return;
        }

        // ignore Firefox/Safari prefetching requests (X-Moz: Prefetch and X-purpose: Preview)
        if ( (isset($_SERVER['HTTP_X_MOZ']) && (strtolower($_SERVER['HTTP_X_MOZ']) == 'prefetch')) ||
            (isset($_SERVER['HTTP_X_PURPOSE']) && (strtolower($_SERVER['HTTP_X_PURPOSE']) == 'preview')) ) {
            return;
        }
        if ( BrowserHelper::is_crawler() ) {
            return;
        }
        if ( isset($_COOKIE['laterpay_tracking_code']) ) {
            list($uniqueId, $control_code) = explode('.', $_COOKIE['laterpay_tracking_code']);

            // make sure only authorized information is recorded
            if ( $control_code != md5($uniqueId . self::$options['secret']) ) {
                return;
            }
        } else {
            $uniqueId = self::getUniqueId();
            setcookie('laterpay_tracking_code', $uniqueId . '.' . md5($uniqueId . self::$options['secret']), time() + self::$options['session_duration'], '/');
        }

        $model = new LaterPayModelPostViews();

        $data = array(
            'post_id'   => $post_id,
            'user_id'   => $uniqueId,
            'date'      => time(),
            'ip'        => 0,
        );
        list($data['ip'], $longOtherIp) = self::getIP2LongRemoteIP();

        $model->updatePostViews($data);
    }

    /**
     * Get Full URL
     *
     * @param array $s $_SERVER
     *
     * @return string URL
     */
    public static function getFullUrl( $s ) {
        if ( !empty($s['HTTPS']) && $s['HTTPS'] == 'on' ) {
            $ssl = true;
        } else {
            $ssl = false;
        }
        $sp = strtolower($s['SERVER_PROTOCOL']);
        if ( $ssl ) {
            $aux = 's';
        } else {
            $aux = '';
        }
        $protocol = substr($sp, 0, strpos($sp, '/')) . ( $aux );
        $port = $s['SERVER_PORT'];
        if ( (!$ssl && $port == '80') || ($ssl && $port == '443') ) {
            $port = '';
        } else {
            $port = ':' . $port;
        }
        if ( isset($s['HTTP_X_FORWARDED_HOST']) ) {
            $host = $s['HTTP_X_FORWARDED_HOST'];
        } else {
            if ( isset($s['HTTP_HOST']) ) {
                $host = $s['HTTP_HOST'];
            } else {
                $host = $s['SERVER_NAME'];
            }
        }

        return $protocol . '://' . $host . $port . $s['REQUEST_URI'];
    }

}
