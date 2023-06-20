<?php

namespace IdeasOnPurpose\WP\MediaLibraryPlus;

/**
 * This class adds controls for improving the WordPress admin Media Grid.
 *
 * Current controls include:
 *   - Column slider - Display 2-12 columns of thumbnail images
 *   - Crop Thumbnails - Toggle between the whole image and a center-cropped thumbnail
 *
 * Turns out it's possible to override the default WordPress Backbone view templates
 * by using a terrible hack: Duplicate IDs. As long as the modified template script
 * appears in the page before the actual script, that will be the one that's used.
 * With duplicate IDs, only the first instance is counted. Subsequent IDs are ignored.
 */
class MediaLibraryPlus
{
    public $action_name = 'Update Sizes';

    public $action_slug = 'update_dimensions_metadata';

    public function __construct()
    {
        add_action('admin_footer', [$this, 'includeFiles']);
        add_action('manage_upload_columns', [$this, 'addColumns']);
        add_action('manage_media_custom_column', [$this, 'renderColumns'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'adminStyles'], 100);

        // TODO: Setting up for sortable columns, store image dimensions in post_meta
        add_filter('wp_generate_attachment_metadata', [$this, 'update_image_postmeta'], 10, 2);
        add_action('bulk_actions-upload', [$this, 'addBulkAction']);
        add_action('media_row_actions', [$this, 'addRowAction'], 10, 2);
        add_action('pre_get_posts', [$this, 'sort_by_column']);
        add_filter('manage_upload_sortable_columns', [$this, 'makeSortable']);

        // Action Handlers
        add_action('handle_bulk_actions-upload', [$this, 'handle_bulk_actions'], 10, 3);
        add_action("admin_post_{$this->action_slug}", [$this, 'handle_row_actions']);

        # Can we add stuff tot the Media Items filter menu by hacking mime types?
        // add_filter('post_mime_types', [$this, 'hack_mime_types']);

        # Hide granular columns by default
        add_filter('default_hidden_columns', [$this, 'hide_columns_default'], 10, 2);
    }

    public function includeFiles()
    {
        if (get_current_screen()->id == 'upload') {
            include __DIR__ . '/includes/scripts.html';
            include __DIR__ . '/includes/dynamic-styles.php';
        }
    }

    // TODO: Attempting to add JPEG, PNG and other formats to the All Media Types filter menu
    // public function hack_mime_types($types)
    // {
    //     @\Kint::$mode_default = \Kint::MODE_CLI;
    //     // @\Sage::$mode_default = \Sage::MODE_CLI;
    //     error_log(@d($types));
    //     @\Kint::$mode_default = \Kint::MODE_RICH;
    //     // @\Sage::$mode_default = \Sage::MODE_RICH;
    //     return $types;
    // }

    public function adminStyles()
    {
        if (get_current_screen()->id == 'upload') {
            $css = file_get_contents(__DIR__ . '/includes/styles.css');
            $css .= ' th#dimensions { width: 15%; }';
            $css .= ' th#width { width: 7em; }';
            $css .= ' th#height { width: 7em; }';
            $css .= ' th#filesize { width: 9em; }';

            // $css .= ' .emoji { font-family: "Segoe UI Emoji"; }';

            wp_add_inline_style('wp-admin', $css);
        }
    }

    public function addColumns($cols)
    {
        $newCols = [];

        foreach ($cols as $key => $value) {
            $newCols[$key] = $value;
            if ($key === 'title') {
                $newCols['dimensions'] = 'Dimensions';
                $newCols['width'] = 'Width';
                $newCols['height'] = 'Height';
                $newCols['filesize'] = 'Filesize';
                $newCols['imagesizes'] = 'Image Sizes';
            }
        }

        return $newCols;
    }

    public function hide_columns_default($cols, $screen)
    {
        if ($screen->id == 'upload') {
            $cols['width'] = 'width';
            $cols['height'] = 'height';
            // $cols['dimensions'] = 'dimensions';
            // $cols['filesize'] = 'filesize';
            $cols['imagesizes'] = 'imagesizes';
        }
        return $cols;
    }

    /**
     * Return an array with the second item being true-ish to default sort by DESC
     * @link https://developer.wordpress.org/reference/classes/wp_list_table/get_sortable_columns/
     */
    public function makeSortable($cols)
    {
        $newCols = $cols;
        $asc = [];
        $desc = ['width', 'height', 'filesize', 'imagesizes', 'dimensions'];
        foreach ($asc as $col) {
            $newCols[$col] = [$col, false];
        }
        foreach ($desc as $col) {
            $newCols[$col] = [$col, true];
        }
        return $newCols;
    }

    public function count_all_images($img_meta)
    {
        $total = array_key_exists('file', $img_meta) ? 1 : 0;
        $total += count($img_meta['sizes']);
        return $total;
    }

    public function get_combined_filesize($id)
    {
        $src = get_attached_file($id, true);
        $total = filesize($src);
        $basedir = dirname($src);
        $metadata = wp_get_attachment_metadata($id);
        if (array_key_exists('sizes', $metadata)) {
            foreach ($metadata['sizes'] as $size) {
                $src = "{$basedir}/{$size['file']}";
                $total += filesize($src);
            }
        }

        $src = wp_get_original_image_path($id, true);
        if ($src) {
            $total += filesize($src);
        }

        return $total;
    }

    public function renderColumns($col, $id)
    {
        $metadata = wp_get_attachment_metadata($id);
        switch ($col) {
            case 'width':
                $val = get_post_meta($id, 'img_width', true);
                if (!$val && $metadata) {
                    if (array_key_exists('width', $metadata)) {
                        $val = $metadata['width'];
                    }
                }
                echo $val ? "{$val}px" : '--';

                $orig_val = get_post_meta($id, 'img_width_original', true);
                if ($orig_val) {
                    echo "<br>({$orig_val}px)";
                }
                break;

            case 'height':
                $val = get_post_meta($id, 'img_height', true);
                if (!$val && $metadata) {
                    if (array_key_exists('height', $metadata)) {
                        $val = $metadata['height'];
                    }
                }
                echo $val ? "{$val}px" : '--';

                $orig_val = get_post_meta($id, 'img_height_original', true);
                if ($orig_val) {
                    echo "<br>({$orig_val}px)";
                }
                break;

            case 'dimensions':
                $template = '%d&nbsp;x&nbsp;%d';

                $w = get_post_meta($id, 'img_width', true);
                $h = get_post_meta($id, 'img_height', true);
                if (!$w && $metadata && array_key_exists('width', $metadata)) {
                    $w = $metadata['width'];
                }
                if (!$h && $metadata && array_key_exists('height', $metadata)) {
                    $h = $metadata['height'];
                }

                if ($w || $h) {
                    printf($template, $w, $h);
                } else {
                    echo '--';
                }

                $ow = get_post_meta($id, 'img_width_original', true);
                $oh = get_post_meta($id, 'img_height_original', true);
                if ($ow || $oh) {
                    printf("<br>({$template})", $ow, $oh);
                }

                break;

            case 'filesize':
                $full_size = get_post_meta($id, 'img_filesize', true);
                $original_size = get_post_meta($id, 'img_filesize_original', true);
                $total_size = get_post_meta($id, 'img_filesize_combined', true);

                $full_size = str_replace(' ', '&nbsp;', size_format($full_size, 1));
                $original_size = str_replace(' ', '&nbsp;', size_format($original_size, 1));
                $total_size = str_replace(' ', '&nbsp;', size_format($total_size, 1));

                $full_url = wp_get_attachment_url($id);

                $combined = $total_size ? "<strong>Total</strong>: {$total_size}<br>\n" : '';
                $original = '';
                $full = $full_size
                    ? "<a href='{$full_url}' target='_blank'>Full</a>: {$full_size}<br>\n"
                    : '';

                if ($metadata && array_key_exists('original_image', $metadata)) {
                    $original_url = wp_get_original_image_url($id);
                    $original = $original_size
                        ? "<a href='{$original_url}' target='_blank'>Original</a>: {$original_size}<br>\n"
                        : '';
                }

                if ($combined || $original || $full) {
                    echo "{$combined}{$original}{$full}";
                } else {
                    $action_url = $this->row_action_url($id);
                    echo "<a href='{$action_url}'>Update Sizes</a>";
                }
                break;

            case 'imagesizes':
                $template = '<span title="Name: %s, Size: %s">%d&nbsp;x&nbsp;%d</span>';
                $sizes = [];

                if (
                    $metadata &&
                    array_key_exists('width', $metadata) &&
                    array_key_exists('height', $metadata)
                ) {
                    $filesize = array_key_exists('filesize', $metadata)
                        ? size_format($metadata['filesize'], 1)
                        : '--';
                    $sizes[$metadata['width'] * $metadata['height']] = sprintf(
                        $template,
                        'full',
                        $filesize,
                        $metadata['width'],
                        $metadata['height']
                    );
                }

                if ($metadata && array_key_exists('sizes', $metadata)) {
                    foreach ($metadata['sizes'] as $label => $img) {
                        if (array_key_exists('width', $img) && array_key_exists('height', $img)) {
                            $filesize = array_key_exists('filesize', $img)
                                ? size_format($img['filesize'], 1)
                                : '--';

                            $sizes[$img['width'] * $img['height']] = sprintf(
                                $template,
                                $label,
                                $filesize,
                                $img['width'],
                                $img['height']
                            );
                        }
                    }
                }

                $orig_w = get_post_meta($id, 'img_width_original', true);
                $orig_h = get_post_meta($id, 'img_height_original', true);
                $orig_size = get_post_meta($id, 'img_filesize_original', true);
                $orig_size = $orig_size ? size_format($orig_size, 1) : '--';
                if ($orig_w && $orig_h) {
                    $sizes[$orig_w * $orig_h] = sprintf(
                        $template,
                        'original',
                        $orig_size,
                        $orig_w,
                        $orig_h
                    );
                }

                if (!empty($sizes)) {
                    krsort($sizes);
                    echo implode("<br>\n", $sizes);
                } else {
                    echo '--';
                }
                break;
        }
    }

    public function update_image_postmeta($metadata, $id)
    {
        $src = get_attached_file($id, true);
        update_post_meta($id, 'img_filesize', filesize($src));

        $pixel_count = 0;
        if (is_array($metadata)) {
            if (array_key_exists('width', $metadata) && array_key_exists('height', $metadata)) {
                update_post_meta($id, 'img_width', $metadata['width']);
                update_post_meta($id, 'img_height', $metadata['height']);
                $pixel_count = $metadata['width'] * $metadata['height'];
            }

            $src = wp_get_original_image_path($id, true);
            if ($src) {
                update_post_meta($id, 'img_filesize_original', filesize($src));

                $dims = getimagesize($src);
                if ($dims) {
                    $pixel_count = max($pixel_count, $dims[0] * $dims[1]);

                    if ($dims[0] === $metadata['width'] && $dims[1] === $metadata['height']) {
                        delete_post_meta($id, 'img_width_original');
                        delete_post_meta($id, 'img_height_original');
                    } else {
                        update_post_meta($id, 'img_width_original', $dims[0]);
                        update_post_meta($id, 'img_height_original', $dims[1]);
                    }
                }
            }

            $total_size = $this->get_combined_filesize($id);
            update_post_meta($id, 'img_filesize_combined', $total_size);
            update_post_meta($id, 'img_pixel_count', $pixel_count);
        }
        return $metadata;
    }

    public function addBulkAction($bulk_actions)
    {
        $bulk_actions[$this->action_slug] = $this->action_name;
        return $bulk_actions;
    }

    public function row_action_url($id)
    {
        return add_query_arg(
            [
                'action' => $this->action_slug,
                'attachment' => $id,
                'return_query' => urlencode($_SERVER['QUERY_STRING']),
            ],
            admin_url('admin-post.php')
        );
    }

    public function addRowAction($row_actions, $post)
    {
        // $action_url = add_query_arg(
        //     [
        //         'action' => $this->action_slug,
        //         'attachment' => $post->ID,
        //         'return_query' => urlencode($_SERVER['QUERY_STRING']),
        //     ],
        //     admin_url('admin-post.php')
        // );
        $action_url = $this->row_action_url($post->IDs);

        $row_actions[$this->action_slug] = sprintf(
            '<a href="%s">%s</a>',
            $action_url,
            $this->action_name
        );
        return $row_actions;
    }

    public function handle_row_actions()
    {
        $id = $_REQUEST['attachment'];
        $return_query = $_REQUEST['return_query'];

        $meta = wp_get_attachment_metadata($id, true);

        $this->update_image_postmeta($meta, $id);

        $redirect_query = [];
        parse_str($return_query, $redirect_query);
        $redirect_query['updated_attachment'] = $id;
        $redirect = add_query_arg($redirect_query, admin_url('upload.php'));
        // $redirect .= "#post-{$id}"; // Adding an ID achror might not work anyway if the sort order changes from the update, the item might no longer appear in that view.
        wp_redirect($redirect);
        exit();
    }

    public function handle_bulk_actions($redirect_to, $do_action, $post_ids)
    {
        if ($do_action !== $this->action_slug) {
            return $redirect_to;
        }
        foreach ($post_ids as $post_id) {
            $meta = wp_get_attachment_metadata($post_id, true);
            $this->update_image_postmeta($meta, $post_id);
        }
        $redirect_to = add_query_arg('updated_posts', count($post_ids), $redirect_to);
        return $redirect_to;
    }

    /**
     * $query is passed by reference, so just modify it, no need to return
     */
    public function sort_by_column($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
            return;
        }
        $sort_keys = [
            'width' => 'img_width',
            'height' => 'img_height',
            'filesize' => 'img_filesize_combined',
            'imagesizes' => 'img_pixel_count',
            'dimensions' => 'img_pixel_count',
        ];

        $orderby = strtolower($query->get('orderby'));
        $order = strtolower($query->get('order')) === 'asc' ? 'asc' : 'desc';
        if (array_key_exists($orderby, $sort_keys)) {
            $meta_query = [
                'relation' => 'OR',
                [
                    'key' => $sort_keys[$orderby],
                    'compare' => 'NOT EXISTS',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => $sort_keys[$orderby],
                    'type' => 'NUMERIC',
                ],
            ];

            $query->set('meta_query', $meta_query);
            $query->set('orderby', 'meta_value');
            $query->set('order', $order);
        }
    }
}
