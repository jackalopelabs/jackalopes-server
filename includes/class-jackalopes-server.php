<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/includes
 */
class Jackalopes_Server {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Jackalopes_Server_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Jackalopes_Server_Loader. Orchestrates the hooks of the plugin.
     * - Jackalopes_Server_Admin. Defines all hooks for the admin area.
     * - Jackalopes_Server_WebSocket. Defines the WebSocket server functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-loader.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once JACKALOPES_SERVER_PLUGIN_DIR . 'admin/class-admin.php';

        /**
         * The class responsible for defining the WebSocket server functionality.
         */
        require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-websocket-server.php';

        /**
         * The class responsible for handling database operations.
         */
        require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-database.php';

        /**
         * The class responsible for defining the REST API endpoints.
         */
        require_once JACKALOPES_SERVER_PLUGIN_DIR . 'public/class-rest-api.php';

        $this->loader = new Jackalopes_Server_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Jackalopes_Server_Admin();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Register AJAX handlers
        $plugin_admin->register_ajax_handlers();
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $rest_api = new Jackalopes_Server_REST_API();

        $this->loader->add_action('rest_api_init', $rest_api, 'register_routes');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
        
        // Auto-start the WebSocket server if enabled
        if (get_option('jackalopes_server_auto_start', '0') === '1') {
            add_action('init', array($this, 'start_websocket_server'));
        }
    }

    /**
     * Start the WebSocket server.
     *
     * @since    1.0.0
     */
    public function start_websocket_server() {
        $websocket_server = new Jackalopes_Server_WebSocket();
        $websocket_server->start();
    }
} 