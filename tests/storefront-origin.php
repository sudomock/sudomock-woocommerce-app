<?php
define( 'ABSPATH', __DIR__ );

function home_url() {
    return 'https://shop.example/';
}

function wp_parse_url( $url ) {
    return parse_url( $url );
}

function absint( $value ) {
    return abs( (int) $value );
}

function wp_unslash( $value ) {
    return $value;
}

require_once dirname( __DIR__ ) . '/includes/class-sudomock-storefront.php';

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$origin = new ReflectionMethod( SudoMock_Storefront::class, 'url_origin' );
check( 'https://shop.example' === $origin->invoke( null, 'HTTPS://SHOP.EXAMPLE:443/path' ), 'origin normalization failed' );
check( 'https://shop.example:8443' === $origin->invoke( null, 'https://shop.example:8443/path' ), 'custom port lost' );
check( '' === $origin->invoke( null, 'javascript://shop.example' ), 'non-http origin accepted' );

$request_origin = new ReflectionMethod( SudoMock_Storefront::class, 'request_has_storefront_origin' );
$_SERVER['HTTP_ORIGIN']  = 'https://shop.example';
$_SERVER['HTTP_REFERER'] = 'https://evil.example/forged';
check( true === $request_origin->invoke( null ), 'exact Origin rejected' );

$_SERVER['HTTP_ORIGIN']  = 'https://evil.example';
$_SERVER['HTTP_REFERER'] = 'https://shop.example/product';
check( false === $request_origin->invoke( null ), 'bad Origin fell back to Referer' );

unset( $_SERVER['HTTP_ORIGIN'] );
check( true === $request_origin->invoke( null ), 'same-origin Referer fallback rejected' );

unset( $_SERVER['HTTP_REFERER'] );
check( false === $request_origin->invoke( null ), 'missing browser origin accepted' );

echo "storefront origin checks passed\n";
