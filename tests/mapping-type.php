<?php
define( 'ABSPATH', __DIR__ );

$sudomock_test_meta = array();
function get_post_meta( $product_id, $key, $single ) {
    global $sudomock_test_meta;
    return isset( $sudomock_test_meta[ $key ] ) ? $sudomock_test_meta[ $key ] : '';
}

require_once __DIR__ . '/../includes/class-sudomock-product.php';

function sudomock_expect_type( $expected ) {
    $actual = SudoMock_Product::get_mockup_type( 1 );
    if ( $expected !== $actual ) {
        throw new RuntimeException( "Expected {$expected}, got {$actual}" );
    }
}

$sudomock_test_meta['_sudomock_mockup_uuid'] = 'legacy-uuid';
sudomock_expect_type( 'psd' );

$sudomock_test_meta['_sudomock_mockup_type'] = '2d';
sudomock_expect_type( '2d' );

$sudomock_test_meta['_sudomock_mockup_type'] = 'browser-choice';
sudomock_expect_type( '' );

echo "mapping type checks passed\n";
