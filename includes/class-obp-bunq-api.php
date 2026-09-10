<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class OBP_Bunq_API {
    private $environment;
    private $access_token;
    private $context_option;
    private $logger;
    private $context = array();
    private $session = null;

    public function __construct( $environment, $access_token, $context_option = 'obp_bunq_api_context_enc', $logger = null ) {
        $this->environment    = $environment === 'sandbox' ? 'sandbox' : 'live';
        $this->access_token   = (string) $access_token;
        $this->context_option = (string) $context_option;
        $this->logger         = $logger;
        $this->context        = $this->load_context();
    }

    public static function oauth_authorize_endpoint( $environment ) {
        return $environment === 'sandbox' ? 'https://oauth.sandbox.bunq.com/auth' : 'https://oauth.bunq.com/auth';
    }

    public static function oauth_token_endpoint( $environment ) {
        return $environment === 'sandbox' ? 'https://api-oauth.sandbox.bunq.com/v1/token' : 'https://api.oauth.bunq.com/v1/token';
    }

    public static function api_base( $environment ) {
        return $environment === 'sandbox' ? 'https://public-api.sandbox.bunq.com/v1' : 'https://api.bunq.com/v1';
    }

    public static function authorization_url( $environment, $client_id, $redirect_uri, $state ) {
        return add_query_arg(
            array(
                'response_type' => 'code',
                'client_id'     => $client_id,
                'redirect_uri'  => $redirect_uri,
                'state'         => $state,
            ),
            self::oauth_authorize_endpoint( $environment )
        );
    }

    public static function exchange_authorization_code( $environment, $client_id, $client_secret, $redirect_uri, $code ) {
        $response = wp_remote_post(
            self::oauth_token_endpoint( $environment ),
            array(
                'timeout' => 30,
                'body'    => array(
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'bunq OAuth token exchange failed: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $json   = json_decode( $body, true );

        if ( $status < 200 || $status >= 300 || ! is_array( $json ) || empty( $json['access_token'] ) ) {
            throw new RuntimeException( 'bunq OAuth token exchange returned an invalid response.' );
        }

        return (string) $json['access_token'];
    }

    public function reset_context() {
        $this->context = array();
        $this->session = null;
        delete_option( $this->context_option );
    }

    public function ensure_context() {
        if ( $this->access_token === '' ) {
            throw new RuntimeException( 'No bunq OAuth access token is available.' );
        }

        $token_fingerprint = hash( 'sha256', $this->access_token );
        if (
            empty( $this->context['private_key'] ) ||
            empty( $this->context['server_public_key'] ) ||
            empty( $this->context['installation_token'] ) ||
            empty( $this->context['environment'] ) ||
            $this->context['environment'] !== $this->environment ||
            empty( $this->context['access_token_fingerprint'] ) ||
            ! hash_equals( $this->context['access_token_fingerprint'], $token_fingerprint )
        ) {
            $this->create_context();
        }

        return true;
    }

    public function open_session() {
        if ( is_array( $this->session ) && ! empty( $this->session['token'] ) && ! empty( $this->session['user_id'] ) ) {
            return $this->session;
        }

        $this->ensure_context();

        $payload = array( 'secret' => $this->access_token );
        $response = $this->signed_request(
            'POST',
            '/session-server',
            $payload,
            $this->context['installation_token']
        );

        $token_obj = self::find_first_object( $response, 'Token' );
        $user_obj  = self::find_first_object( $response, 'UserApiKey' );

        if ( ! is_array( $user_obj ) ) {
            $user_obj = self::find_first_object( $response, 'UserCompany' );
        }
        if ( ! is_array( $user_obj ) ) {
            $user_obj = self::find_first_object( $response, 'UserPerson' );
        }

        $token   = is_array( $token_obj ) && ! empty( $token_obj['token'] ) ? (string) $token_obj['token'] : '';
        $user_id = is_array( $user_obj ) && ! empty( $user_obj['id'] ) ? (int) $user_obj['id'] : 0;

        if ( $token === '' || $user_id <= 0 ) {
            throw new RuntimeException( 'bunq session response did not contain a usable token/user id.' );
        }

        $this->session = array(
            'token'   => $token,
            'user_id' => $user_id,
        );

        return $this->session;
    }

    public function list_monetary_accounts() {
        $session = $this->open_session();
        $response = $this->signed_request(
            'GET',
            '/user/' . rawurlencode( (string) $session['user_id'] ) . '/monetary-account',
            null,
            $session['token']
        );

        // The generic endpoint can return several MonetaryAccount* object types.
        $objects = self::find_all_objects_prefix( $response, 'MonetaryAccount' );
        $accounts = array();
        foreach ( $objects as $object ) {
            if ( empty( $object['id'] ) ) { continue; }
            $id = (int) $object['id'];
            $iban = '';
            if ( ! empty( $object['alias'] ) && is_array( $object['alias'] ) ) {
                foreach ( $object['alias'] as $alias ) {
                    if ( is_array( $alias ) && isset( $alias['type'], $alias['value'] ) && strtoupper( (string) $alias['type'] ) === 'IBAN' ) {
                        $iban = (string) $alias['value']; break;
                    }
                }
            }
            $label = ! empty( $object['description'] ) ? (string) $object['description'] : ( 'bunq account ' . $id );
            if ( $iban !== '' ) { $label .= ' — ' . $iban; }
            $accounts[ $id ] = $label;
        }
        return $accounts;
    }

    public function create_request_inquiry( $monetary_account_id, $amount, $currency, $description, $merchant_reference, $redirect_url ) {
        $session = $this->open_session();
        $payload = array(
            'amount_inquired' => array(
                'value'    => number_format( (float) $amount, 2, '.', '' ),
                'currency' => strtoupper( (string) $currency ),
            ),
            'description'        => self::truncate( (string) $description, 9000 ),
            'merchant_reference' => self::truncate( (string) $merchant_reference, 140 ),
            'allow_bunqme'       => true,
            'redirect_url'       => esc_url_raw( $redirect_url ),
        );

        $path = '/user/' . rawurlencode( (string) $session['user_id'] ) .
            '/monetary-account/' . rawurlencode( (string) (int) $monetary_account_id ) .
            '/request-inquiry';

        $response = $this->signed_request( 'POST', $path, $payload, $session['token'] );
        $request = self::find_first_object( $response, 'RequestInquiry' );
        $id = 0;

        if ( is_array( $request ) && ! empty( $request['id'] ) ) {
            $id = (int) $request['id'];
        }
        if ( $id <= 0 ) {
            $id_obj = self::find_first_object( $response, 'Id' );
            if ( is_array( $id_obj ) && ! empty( $id_obj['id'] ) ) {
                $id = (int) $id_obj['id'];
            }
        }
        if ( $id <= 0 ) {
            throw new RuntimeException( 'bunq did not return a RequestInquiry id.' );
        }

        if ( ! is_array( $request ) || empty( $request['bunqme_share_url'] ) ) {
            $request = $this->get_request_inquiry( $monetary_account_id, $id );
        }

        $url = is_array( $request ) && ! empty( $request['bunqme_share_url'] ) ? (string) $request['bunqme_share_url'] : '';
        if ( $url === '' ) {
            $url = self::find_first_scalar_by_key( $response, 'bunqme_share_url' );
        }
        if ( $url === '' ) {
            throw new RuntimeException( 'bunq did not return a payment URL for the RequestInquiry.' );
        }

        return array(
            'id'      => $id,
            'url'     => $url,
            'request' => $request,
        );
    }

    public function get_request_inquiry( $monetary_account_id, $request_id ) {
        $session = $this->open_session();
        $path = '/user/' . rawurlencode( (string) $session['user_id'] ) .
            '/monetary-account/' . rawurlencode( (string) (int) $monetary_account_id ) .
            '/request-inquiry/' . rawurlencode( (string) (int) $request_id );

        $response = $this->signed_request( 'GET', $path, null, $session['token'] );
        $request  = self::find_first_object( $response, 'RequestInquiry' );
        if ( ! is_array( $request ) ) {
            throw new RuntimeException( 'bunq RequestInquiry response was not recognized.' );
        }
        return $request;
    }

    public function merge_notification_filters( $callback_url ) {
        $session = $this->open_session();
        $path = '/user/' . rawurlencode( (string) $session['user_id'] ) . '/notification-filter-url';
        $filters = array();

        // Fail closed: never overwrite bunq notification filters unless the existing state was read successfully.
        $existing = $this->signed_request( 'GET', $path, null, $session['token'] );
        $all = self::find_all_objects( $existing, 'NotificationFilterUrl' );
        foreach ( $all as $object ) {
            if ( ! empty( $object['notification_filters'] ) && is_array( $object['notification_filters'] ) ) {
                foreach ( $object['notification_filters'] as $filter ) {
                    if ( is_array( $filter ) && ! empty( $filter['category'] ) && ! empty( $filter['notification_target'] ) ) {
                        $filters[] = array(
                            'category'            => (string) $filter['category'],
                            'notification_target' => (string) $filter['notification_target'],
                        );
                    }
                }
            }
        }

        foreach ( array( 'REQUEST', 'BUNQME_TAB', 'MUTATION', 'OAUTH' ) as $category ) {
            $found = false;
            foreach ( $filters as $filter ) {
                if ( $filter['category'] === $category && $filter['notification_target'] === $callback_url ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $filters[] = array(
                    'category'            => $category,
                    'notification_target' => $callback_url,
                );
            }
        }

        $dedup = array();
        foreach ( $filters as $filter ) {
            $key = strtoupper( $filter['category'] ) . '|' . $filter['notification_target'];
            $dedup[ $key ] = $filter;
        }

        return $this->signed_request(
            'POST',
            $path,
            array( 'notification_filters' => array_values( $dedup ) ),
            $session['token']
        );
    }


    public function remove_notification_target( $callback_url ) {
        $session = $this->open_session();
        $path = '/user/' . rawurlencode( (string) $session['user_id'] ) . '/notification-filter-url';
        $existing = $this->signed_request( 'GET', $path, null, $session['token'] );
        $filters = array();
        $all = self::find_all_objects( $existing, 'NotificationFilterUrl' );
        foreach ( $all as $object ) {
            if ( empty( $object['notification_filters'] ) || ! is_array( $object['notification_filters'] ) ) {
                continue;
            }
            foreach ( $object['notification_filters'] as $filter ) {
                if ( ! is_array( $filter ) || empty( $filter['category'] ) || empty( $filter['notification_target'] ) ) {
                    continue;
                }
                if ( (string) $filter['notification_target'] === (string) $callback_url ) {
                    continue;
                }
                $filters[] = array(
                    'category'            => (string) $filter['category'],
                    'notification_target' => (string) $filter['notification_target'],
                );
            }
        }
        return $this->signed_request(
            'POST',
            $path,
            array( 'notification_filters' => array_values( $filters ) ),
            $session['token']
        );
    }

    public static function request_status( array $request ) {
        return strtoupper( isset( $request['status'] ) ? (string) $request['status'] : '' );
    }

    public static function request_amount( array $request ) {
        $amount = array();
        if ( ! empty( $request['amount_responded'] ) && is_array( $request['amount_responded'] ) ) {
            $amount = $request['amount_responded'];
        } elseif ( ! empty( $request['amount_inquired'] ) && is_array( $request['amount_inquired'] ) ) {
            $amount = $request['amount_inquired'];
        }
        return array(
            'value'    => isset( $amount['value'] ) ? (string) $amount['value'] : '',
            'currency' => isset( $amount['currency'] ) ? strtoupper( (string) $amount['currency'] ) : '',
        );
    }

    private function create_context() {
        $key_config = array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        );
        $key = openssl_pkey_new( $key_config );
        if ( ! $key ) {
            throw new RuntimeException( 'Unable to generate the bunq RSA key pair.' );
        }

        $private_key = '';
        if ( ! openssl_pkey_export( $key, $private_key ) ) {
            throw new RuntimeException( 'Unable to export the bunq RSA private key.' );
        }
        $details = openssl_pkey_get_details( $key );
        if ( ! is_array( $details ) || empty( $details['key'] ) ) {
            throw new RuntimeException( 'Unable to export the bunq RSA public key.' );
        }
        $public_key = (string) $details['key'];

        $installation = $this->unsigned_installation_request( $public_key );
        $token_obj = self::find_first_object( $installation, 'Token' );
        $server_obj = self::find_first_object( $installation, 'ServerPublicKey' );
        $installation_token = is_array( $token_obj ) && ! empty( $token_obj['token'] ) ? (string) $token_obj['token'] : '';
        $server_public_key = is_array( $server_obj ) && ! empty( $server_obj['server_public_key'] ) ? (string) $server_obj['server_public_key'] : '';

        if ( $installation_token === '' || $server_public_key === '' ) {
            throw new RuntimeException( 'bunq installation response did not contain its token/public key.' );
        }

        $this->context = array(
            'environment'              => $this->environment,
            'private_key'              => $private_key,
            'public_key'               => $public_key,
            'server_public_key'        => $server_public_key,
            'installation_token'       => $installation_token,
            'access_token_fingerprint' => hash( 'sha256', $this->access_token ),
            'created_at'               => time(),
        );

        $payload = array(
            'description' => 'Open bunq Payments ' . wp_parse_url( home_url(), PHP_URL_HOST ),
            'secret'      => $this->access_token,
        );

        $device = $this->signed_request( 'POST', '/device-server', $payload, $installation_token );
        $id_obj = self::find_first_object( $device, 'Id' );
        $device_id = is_array( $id_obj ) && ! empty( $id_obj['id'] ) ? (int) $id_obj['id'] : 0;
        if ( $device_id <= 0 ) {
            $scalar_id = self::find_first_scalar_by_key( $device, 'id' );
            $device_id = $scalar_id !== '' ? (int) $scalar_id : 0;
        }
        $this->context['device_server_id'] = $device_id;
        $this->save_context();
        $this->session = null;
    }

    private function unsigned_installation_request( $public_key ) {
        $url = self::api_base( $this->environment ) . '/installation';
        $body = wp_json_encode( array( 'client_public_key' => $public_key ), JSON_UNESCAPED_SLASHES );
        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'POST',
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Cache-Control' => 'no-cache',
                    'User-Agent'    => $this->user_agent(),
                ),
                'body'    => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'bunq installation request failed: ' . $response->get_error_message() );
        }
        $status = wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $json = json_decode( $raw, true );
        if ( $status < 200 || $status >= 300 || ! is_array( $json ) ) {
            throw new RuntimeException( 'bunq installation request returned HTTP ' . (int) $status . '.' );
        }
        return $json;
    }

    private function signed_request( $method, $path, $payload, $auth_token ) {
        $method = strtoupper( $method );
        $body = $payload === null ? '' : wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        if ( $body === false ) {
            throw new RuntimeException( 'Unable to encode bunq API request.' );
        }

        if ( empty( $this->context['private_key'] ) || empty( $this->context['server_public_key'] ) ) {
            throw new RuntimeException( 'bunq API context is incomplete.' );
        }

        $signature = '';
        $private_key = openssl_pkey_get_private( $this->context['private_key'] );
        if ( ! $private_key || ! openssl_sign( $body, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
            throw new RuntimeException( 'Unable to sign bunq API request.' );
        }

        $headers = array(
            'Content-Type'                 => 'application/json',
            'Cache-Control'                => 'no-cache',
            'User-Agent'                   => $this->user_agent(),
            'X-Bunq-Language'              => 'nl_NL',
            'X-Bunq-Region'                => 'nl_NL',
            'X-Bunq-Geolocation'           => '0 0 0 0 000',
            'X-Bunq-Client-Request-Id'     => wp_generate_uuid4(),
            'X-Bunq-Client-Authentication' => $auth_token,
            'X-Bunq-Client-Signature'      => base64_encode( $signature ),
        );

        $args = array(
            'method'      => $method,
            'timeout'     => 30,
            'redirection' => 0,
            'headers'     => $headers,
        );
        if ( $method !== 'GET' || $body !== '' ) {
            $args['body'] = $body;
        }

        $response = wp_remote_request( self::api_base( $this->environment ) . '/' . ltrim( $path, '/' ), $args );
        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'bunq API request failed: ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $server_signature = wp_remote_retrieve_header( $response, 'x-bunq-server-signature' );

        if ( ! $this->verify_server_signature( $raw, $server_signature ) ) {
            throw new RuntimeException( 'bunq API response signature verification failed.' );
        }

        $json = $raw === '' ? array() : json_decode( $raw, true );
        if ( $raw !== '' && ! is_array( $json ) ) {
            throw new RuntimeException( 'bunq API returned non-JSON data.' );
        }

        if ( $status < 200 || $status >= 300 ) {
            $message = self::extract_error_message( $json );
            throw new RuntimeException( 'bunq API returned HTTP ' . (int) $status . ( $message ? ': ' . $message : '.' ) );
        }

        return $json;
    }

    private function verify_server_signature( $body, $encoded_signature ) {
        if ( ! is_string( $encoded_signature ) || $encoded_signature === '' ) {
            return false;
        }
        $signature = base64_decode( $encoded_signature, true );
        if ( $signature === false ) {
            return false;
        }
        $public_key = openssl_pkey_get_public( $this->context['server_public_key'] );
        if ( ! $public_key ) {
            return false;
        }
        return openssl_verify( (string) $body, $signature, $public_key, OPENSSL_ALGO_SHA256 ) === 1;
    }

    private function save_context() {
        update_option( $this->context_option, OBP_Crypto::encrypt( wp_json_encode( $this->context ) ), false );
    }

    private function load_context() {
        $encoded = get_option( $this->context_option, '' );
        $plain = OBP_Crypto::decrypt( $encoded );
        if ( $plain === '' ) {
            return array();
        }
        $json = json_decode( $plain, true );
        return is_array( $json ) ? $json : array();
    }

    private function user_agent() {
        return 'Open-bunq-Payments/' . ( defined( 'OBP_VERSION' ) ? OBP_VERSION : '3.0' ) . ' ' . wp_parse_url( home_url(), PHP_URL_HOST );
    }

    private function log( $level, $message, array $context = array() ) {
        if ( is_callable( $this->logger ) ) {
            call_user_func( $this->logger, $level, $message, $context );
        }
    }

    public static function find_first_object( $node, $key ) {
        if ( ! is_array( $node ) ) {
            return null;
        }
        if ( array_key_exists( $key, $node ) && is_array( $node[ $key ] ) ) {
            return $node[ $key ];
        }
        foreach ( $node as $value ) {
            if ( is_array( $value ) ) {
                $found = self::find_first_object( $value, $key );
                if ( is_array( $found ) ) {
                    return $found;
                }
            }
        }
        return null;
    }

    public static function find_all_objects( $node, $key ) {
        $found = array();
        if ( ! is_array( $node ) ) {
            return $found;
        }
        if ( array_key_exists( $key, $node ) && is_array( $node[ $key ] ) ) {
            $found[] = $node[ $key ];
        }
        foreach ( $node as $value ) {
            if ( is_array( $value ) ) {
                $found = array_merge( $found, self::find_all_objects( $value, $key ) );
            }
        }
        return $found;
    }


    public static function find_all_objects_prefix( $node, $prefix ) {
        $found = array();
        if ( ! is_array( $node ) ) { return $found; }
        foreach ( $node as $key => $value ) {
            if ( is_string( $key ) && strpos( $key, $prefix ) === 0 && is_array( $value ) ) { $found[] = $value; }
            if ( is_array( $value ) ) { $found = array_merge( $found, self::find_all_objects_prefix( $value, $prefix ) ); }
        }
        return $found;
    }

    public static function find_first_scalar_by_key( $node, $key ) {
        if ( ! is_array( $node ) ) {
            return '';
        }
        if ( array_key_exists( $key, $node ) && is_scalar( $node[ $key ] ) ) {
            return (string) $node[ $key ];
        }
        foreach ( $node as $value ) {
            if ( is_array( $value ) ) {
                $found = self::find_first_scalar_by_key( $value, $key );
                if ( $found !== '' ) {
                    return $found;
                }
            }
        }
        return '';
    }


    private static function truncate( $value, $length ) {
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
    }

    private static function extract_error_message( $node ) {
        if ( ! is_array( $node ) ) {
            return '';
        }
        foreach ( array( 'error_description', 'message', 'error' ) as $key ) {
            if ( isset( $node[ $key ] ) && is_scalar( $node[ $key ] ) ) {
                return sanitize_text_field( (string) $node[ $key ] );
            }
        }
        foreach ( $node as $value ) {
            if ( is_array( $value ) ) {
                $message = self::extract_error_message( $value );
                if ( $message !== '' ) {
                    return $message;
                }
            }
        }
        return '';
    }
}
