<?php
/**
 * Plugin Name: Listicle JSON-LD Schema
 * Plugin URI: https://yuko.so/
 * Description: Adds ItemList JSON-LD structured data to listicle posts for better SEO and EEAT signals
 * Version: 2.0.0
 * Author: Yegappan
 * Author URI: https://yuko.so/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: listicle-schema
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Listicle_Schema_Plugin {
    
    private $meta_key = '_listicle_schema_items';
    private $enable_key = '_listicle_schema_enable';
    private $list_name_key = '_listicle_schema_name';
    private $order_key = '_listicle_schema_order';
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
        add_action('wp_head', array($this, 'output_json_ld'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_detect_list_items', array($this, 'ajax_detect_list_items'));
    }
    
    public function add_meta_box() {
        add_meta_box(
            'listicle_schema_meta_box',
            'Listicle Schema (JSON-LD)',
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('listicle_schema_nonce', 'listicle_schema_nonce_field');
        
        $enabled = get_post_meta($post->ID, $this->enable_key, true);
        $list_name = get_post_meta($post->ID, $this->list_name_key, true);
        $items = get_post_meta($post->ID, $this->meta_key, true);
        $order = get_post_meta($post->ID, $this->order_key, true);
        
        if (empty($items)) {
            $items = array();
        }
        
        if (empty($list_name)) {
            $list_name = get_the_title($post->ID);
        }
        
        if (empty($order)) {
            $order = 'ascending';
        }
        
        ?>
        <div id="listicle-schema-container">
            <div style="margin-bottom: 20px;">
                <label>
                    <input type="checkbox" name="listicle_schema_enable" value="1" <?php checked($enabled, '1'); ?> />
                    <strong>Enable ItemList Schema for this post</strong>
                </label>
            </div>
            
            <div id="listicle-schema-fields" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                <div style="margin-bottom: 15px;">
                    <label for="listicle_schema_name"><strong>List Name:</strong></label><br>
                    <input type="text" id="listicle_schema_name" name="listicle_schema_name" value="<?php echo esc_attr($list_name); ?>" style="width: 100%;" placeholder="e.g., Top 11 Rivo Alternatives for Shopify">
                    <p class="description">This will be used as the "name" in the JSON-LD schema</p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="listicle_schema_order"><strong>List Order:</strong></label><br>
                    <select id="listicle_schema_order" name="listicle_schema_order" style="width: 100%;">
                        <option value="ascending" <?php selected($order, 'ascending'); ?>>Ascending (1, 2, 3...)</option>
                        <option value="descending" <?php selected($order, 'descending'); ?>>Descending (highest to lowest)</option>
                        <option value="unordered" <?php selected($order, 'unordered'); ?>>Unordered (no specific order)</option>
                    </select>
                    <p class="description">How your list items are ordered</p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <button type="button" id="detect-list-items" class="button button-secondary">
                        Auto-Detect List Items from Content
                    </button>
                    <span id="detect-loading" style="display:none; margin-left: 10px;">
                        <span class="spinner is-active" style="float:none; margin:0;"></span>
                        Analyzing content...
                    </span>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <strong>List Items:</strong>
                    <button type="button" id="add-list-item" class="button button-secondary" style="margin-left: 10px;">Add Item</button>
                </div>
                
                <div id="list-items-container">
                    <?php
                    if (!empty($items)) {
                        foreach ($items as $index => $item) {
                            $this->render_list_item($index, $item);
                        }
                    } else {
                        $this->render_list_item(0, array('name' => '', 'url' => ''));
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <script type="text/html" id="list-item-template">
            <?php $this->render_list_item('{{INDEX}}', array('name' => '', 'url' => '')); ?>
        </script>
        <?php
    }
    
    private function render_list_item($index, $item) {
        $name = isset($item['name']) ? $item['name'] : '';
        $url = isset($item['url']) ? $item['url'] : '';
        $position = is_numeric($index) ? ($index + 1) : '{{POSITION}}';
        ?>
        <div class="list-item-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px;">
            <div style="display: table; width: 100%;">
                <div style="display: table-cell; vertical-align: middle; width: 40px;">
                    <span class="item-number" style="font-weight: bold;">#<span class="position-number"><?php echo esc_html($position); ?></span></span>
                </div>
                
                <div style="display: table-cell; vertical-align: middle; padding: 0 10px;">
                    <input type="text" 
                           name="listicle_items[<?php echo esc_attr($index); ?>][name]" 
                           value="<?php echo esc_attr($name); ?>" 
                           placeholder="Item name (e.g., Yuko)" 
                           style="width: 100%; margin-bottom: 5px; padding: 6px;">
                    
                    <input type="text" 
                           name="listicle_items[<?php echo esc_attr($index); ?>][url]" 
                           value="<?php echo esc_attr($url); ?>" 
                           placeholder="URL (e.g., https://yuko.so/)" 
                           style="width: 100%; padding: 6px;">
                </div>
                
                <div style="display: table-cell; vertical-align: middle; width: 80px; text-align: right;">
                    <button type="button" class="button button-small remove-item" style="color: #dc3232;">Remove</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save_meta_box($post_id) {
        if (!isset($_POST['listicle_schema_nonce_field']) || 
            !wp_verify_nonce($_POST['listicle_schema_nonce_field'], 'listicle_schema_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $enabled = isset($_POST['listicle_schema_enable']) ? '1' : '0';
        update_post_meta($post_id, $this->enable_key, $enabled);
        
        if (isset($_POST['listicle_schema_name'])) {
            update_post_meta($post_id, $this->list_name_key, sanitize_text_field($_POST['listicle_schema_name']));
        }
        
        if (isset($_POST['listicle_schema_order'])) {
            $order = sanitize_text_field($_POST['listicle_schema_order']);
            if (in_array($order, array('ascending', 'descending', 'unordered'))) {
                update_post_meta($post_id, $this->order_key, $order);
            }
        }
        
        if (isset($_POST['listicle_items']) && is_array($_POST['listicle_items'])) {
            $items = array();
            
            foreach ($_POST['listicle_items'] as $item) {
                $name = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                $url = isset($item['url']) ? esc_url_raw($item['url']) : '';
                
                if (!empty($name) && !empty($url)) {
                    $items[] = array(
                        'name' => $name,
                        'url' => $url
                    );
                }
            }
            
            update_post_meta($post_id, $this->meta_key, $items);
        }
    }
    
    public function output_json_ld() {
        if (!is_single()) {
            return;
        }
        
        $post_id = get_the_ID();
        $enabled = get_post_meta($post_id, $this->enable_key, true);
        
        if ($enabled !== '1') {
            return;
        }
        
        $list_name = get_post_meta($post_id, $this->list_name_key, true);
        $items = get_post_meta($post_id, $this->meta_key, true);
        $order = get_post_meta($post_id, $this->order_key, true);
        
        if (empty($order)) {
            $order = 'ascending';
        }
        
        if (empty($items) || !is_array($items) || count($items) === 0) {
            return;
        }
        
        $order_map = array(
            'ascending' => 'https://schema.org/ItemListOrderAscending',
            'descending' => 'https://schema.org/ItemListOrderDescending',
            'unordered' => 'https://schema.org/ItemListUnordered'
        );
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => !empty($list_name) ? $list_name : get_the_title($post_id),
            'url' => get_permalink($post_id),
            'itemListOrder' => isset($order_map[$order]) ? $order_map[$order] : $order_map['ascending'],
            'numberOfItems' => count($items),
            'itemListElement' => array()
        );
        
        foreach ($items as $index => $item) {
            $schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $item['url'],
                'name' => $item['name']
            );
        }
        
        echo "\n<!-- Listicle Schema Plugin by Yegappan | https://yuko.so/ -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
        echo "<!-- /Listicle Schema Plugin -->\n\n";
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_style(
            'listicle-schema-admin-style',
            plugin_dir_url(__FILE__) . 'admin-style.css',
            array(),
            '2.0.0'
        );
        
        wp_enqueue_script(
            'listicle-schema-admin',
            plugin_dir_url(__FILE__) . 'admin-script.js',
            array('jquery'),
            '2.0.0',
            true
        );
        
        wp_localize_script('listicle-schema-admin', 'listicleSchema', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('listicle_schema_ajax')
        ));
    }
    
    public function ajax_detect_list_items() {
        check_ajax_referer('listicle_schema_ajax', 'nonce');
        
        if (!isset($_POST['post_id'])) {
            wp_send_json_error('No post ID provided');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        $content = $post->post_content;
        $detected_items = array();
        
        if (has_blocks($content)) {
            $content = do_blocks($content);
        }
        
        $content = apply_filters('the_content', $content);
        
        // Method 1: H2/H3 headings with links
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $content, $heading_matches)) {
            foreach ($heading_matches[1] as $heading_content) {
                $item = $this->parse_list_item($heading_content);
                if ($item) {
                    $detected_items[] = $item;
                }
            }
        }
        
        // Method 2: Numbered headings (all levels)
        if (empty($detected_items)) {
            if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $all_heading_matches)) {
                foreach ($all_heading_matches[1] as $heading_content) {
                    if (preg_match('/^\d+[\.\)\:]\s*/', strip_tags($heading_content, '<a>'))) {
                        $item = $this->parse_list_item($heading_content);
                        if ($item) {
                            $detected_items[] = $item;
                        }
                    }
                }
            }
        }
        
        if (empty($detected_items)) {
            wp_send_json_error('No list items detected. Please add items manually.');
        }
        
        if (count($detected_items) > 50) {
            $detected_items = array_slice($detected_items, 0, 50);
        }
        
        wp_send_json_success(array('items' => $detected_items));
    }
    
    private function parse_list_item($html) {
        $text = strip_tags($html, '<a>');
        
        if (preg_match('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $text, $link_match)) {
            $url = $link_match[1];
            $name = strip_tags($link_match[2]);
            $name = trim($name);
            $name = preg_replace('/^\d+[\.\)\:]\s*/', '', $name);
            
            if (strlen($name) < 2) {
                return null;
            }
            
            if (empty($url) || $url === '#' || strpos($url, '#') === 0) {
                return null;
            }
            
            if (!empty($name) && !empty($url)) {
                return array(
                    'name' => $name,
                    'url' => $url
                );
            }
        }
        
        return null;
    }
}

new Listicle_Schema_Plugin();
