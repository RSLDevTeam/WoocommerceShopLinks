<?php
/**
 * Template Name: Order Link Verification
 */

get_header();
?>
<div id="main-content">
    <div class="container">
        <div id="content-area" class="<?php extra_sidebar_class(); ?> clearfix">
            <div class="et_pb_extra_column_main">
                <?php
                // Get query variables
                $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
                $order_item_id = isset($_GET['order_item_id']) ? absint($_GET['order_item_id']) : 0;

                $valid = false;
                $error_message = '';

                if ($order_id && $order_item_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        foreach ($order->get_items() as $item_id => $item) {
                            if ((int) $item_id === $order_item_id) {
                                $valid = true;
                                break;
                            }
                        }
                        if (!$valid) {
                            $error_message = 'Order item not found for this order.';
                        }
                    } else {
                        $error_message = 'Order not found.';
                    }
                } else {
                    $error_message = 'Missing order ID or order item ID.';
                }
                ?>

                <?php if ($valid) : ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                        <div class="post-wrap">
                            <?php if (is_post_extra_title_meta_enabled()) : ?>
                                <h1 class="entry-title"><?php the_title(); ?></h1>
                            <?php endif; ?>
                            <div class="post-content entry-content">
                                <?php
                                the_content();
                                if (!extra_is_builder_built()) {
                                    wp_link_pages([
                                        'before' => '<div class="page-links">' . esc_html__('Pages:', 'extra'),
                                        'after'  => '</div>',
                                    ]);
                                }
                                ?>
                            </div>
                        </div><!-- /.post-wrap -->
                    </article>
                <?php else : ?>
                    <div class="order-link-error" style="padding: 20px; background-color: #ffe5e5; border: 1px solid #cc0000; color: #cc0000; margin-top: 20px;">
                        <strong>Error:</strong> <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php
                if ((comments_open() || get_comments_number()) && 'on' === et_get_option('extra_show_pagescomments', 'on')) {
                    comments_template('', true);
                }
                ?>
            </div><!-- /.et_pb_extra_column.et_pb_extra_column_main -->

            <?php get_sidebar(); ?>

        </div> <!-- #content-area -->
    </div> <!-- .container -->
</div> <!-- #main-content -->

<?php get_footer(); ?>