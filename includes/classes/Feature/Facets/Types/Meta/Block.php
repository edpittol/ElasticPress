<?php
/**
 * Facets block
 *
 * @since 4.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Meta;

use ElasticPress\Features;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets block class
 */
class Block {
	/**
	 * Hook block funcionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Setup REST endpoints for the feature.
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/meta/keys',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'get_rest_registered_metakeys' ],
			]
		);
	}

	/**
	 * Check permissions of the /facets/taxonomies REST endpoint.
	 *
	 * @return WP_Error|true
	 */
	public function check_facets_meta_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Return an array of registered meta keys.
	 *
	 * @return array
	 */
	public function get_rest_registered_metakeys() {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		try {
			$meta_keys = $post_indexable->get_distinct_meta_field_keys();
		} catch ( \Throwable $th ) {
			$meta_keys = [];
		}

		return $meta_keys;
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/meta',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * The block script is registered here to work around an issue with translations.
	 *
	 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
	 * @return void
	 */
	public function enqueue_editor_assets() {
		wp_register_script(
			'ep-facets-meta-block-script',
			EP_URL . 'dist/js/facets-meta-block-script.js',
			Utils\get_asset_info( 'facets-meta-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-meta-block-script', 'elasticpress' );
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render_block( $attributes ) {
		global $wp_query;

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta', 'block', $attributes );
		$renderer       = new $renderer_class();

		if ( $attributes['isPreview'] ) {
			add_filter( 'ep_is_facetable', '__return_true' );

			add_filter(
				'ep_facet_meta_fields',
				function ( $meta_fields ) use ( $attributes ) {
					$meta_fields = [ $attributes['facet'] ];
					return $meta_fields;
				}
			);

			$search = Features::factory()->get_registered_feature( 'search' );

			$args = [
				'posts_per_page' => 1,
				'post_type'      => $search->get_searchable_post_types(),
			];

			$wp_query->query( $args );
		}

		ob_start();

		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => 'wp-block-elasticpress-facet' ] );
		?>
		<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
			<?php $renderer->render( [], $attributes ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
