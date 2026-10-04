<?php
define( 'ABSPATH', __DIR__ );

// Stands in for WordPress hooks: remembers what the plugin registers and calls
// it with only the arguments it asked for, as WordPress does.
$sudomock_test_filters = array();
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    global $sudomock_test_filters;
    $sudomock_test_filters[ $hook ][] = array( $callback, $accepted_args );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    add_filter( $hook, $callback, $priority, $accepted_args );
}
function apply_filters( $hook, $value, ...$args ) {
    global $sudomock_test_filters;
    $registered = isset( $sudomock_test_filters[ $hook ] ) ? $sudomock_test_filters[ $hook ] : array();
    foreach ( $registered as $entry ) {
        $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
    }
    return $value;
}
function __( $value ) { return $value; }

require_once __DIR__ . '/../includes/class-sudomock-cart.php';

function check( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

// The image WooCommerce hands the block cart for a line: the product's own.
function product_images() {
    return array(
        (object) array(
            'id'               => 77,
            'src'              => 'https://shop.example/uploads/mug.jpg',
            'thumbnail'        => 'https://shop.example/uploads/mug-300x300.jpg',
            'srcset'           => 'https://shop.example/uploads/mug.jpg 1200w',
            'sizes'            => '(max-width: 1200px) 100vw, 1200px',
            'thumbnail_srcset' => 'https://shop.example/uploads/mug-300x300.jpg 300w',
            'thumbnail_sizes'  => '(max-width: 300px) 100vw, 300px',
            'name'             => 'mug',
            'alt'              => 'White mug',
        ),
    );
}

SudoMock_Cart::get_instance();

// A customized line: the block cart shows the first image, so that image must
// be the preview, in a form WooCommerce keeps and with no product photo sizes.
$preview = 'https://cdn.example/files/preview.png';
$images  = apply_filters(
    'woocommerce_store_api_cart_item_images',
    product_images(),
    array( 'sudomock_customization' => array( 'preview_url' => $preview ) ),
    'cart-item-key'
);
$image   = is_array( $images ) && isset( $images[0] ) ? $images[0] : null;
check( is_object( $image ) && isset( $image->id ), 'block cart would discard the customized line image' );
check( $preview === $image->thumbnail && $preview === $image->src, 'customized line image is not the preview' );
check( '' === $image->thumbnail_srcset, 'customized line image still offers sizes of the product photo' );

// A plain line keeps the images it was given.
$given  = product_images();
$images = apply_filters(
    'woocommerce_store_api_cart_item_images',
    $given,
    array( 'product_id' => 100 ),
    'cart-item-key'
);
check( $given === $images, 'plain line images were changed' );

echo "block cart image checks passed\n";
