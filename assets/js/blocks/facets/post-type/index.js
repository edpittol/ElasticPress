/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import icon from '../common/icon';

/**
 * Internal dependencies.
 */
import edit from './edit';
import block from './block.json';

registerBlockType(block, {
	edit,
	save: () => {},
	icon,
});
