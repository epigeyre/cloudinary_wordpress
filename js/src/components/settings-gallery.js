import React from 'react';
import Dot from 'dot-object';
import { render, useEffect, useState } from '@wordpress/element';
import attributes from '../gallery-block/attributes';
import GalleryControls from '../gallery-block/controls';
import { setupAttributesForRendering } from '../gallery-block/utils';

const dot = new Dot( '_' );

delete attributes.cloudName;
delete attributes.mediaAssets;

const parsedAttrs = {};
Object.keys( attributes ).forEach( ( attr ) => {
	parsedAttrs[ attr ] = attributes[ attr ]?.default;
} );

const StatefulGalleryControls = () => {
	const [ statefulAttrs, setStatefulAttrs ] = useState( parsedAttrs );

	const setAttributes = ( attrs ) => {
		setStatefulAttrs( {
			...statefulAttrs,
			...attrs,
		} );
	};

	useEffect( () => {
		const { config } = setupAttributesForRendering( statefulAttrs );

		const gallery = cloudinary.galleryWidget( {
			cloudName: 'demo',
			mediaAssets: [
				{
					tag: 'shoes_product_gallery_demo',
					mediaType: 'image',
				},
			],
			...config,
			container: '.gallery-preview',
		} );

		gallery.render();

		const hiddenField = document.getElementById( 'gallery_settings_input' );

		if ( hiddenField ) {
			hiddenField.value = JSON.stringify( config );
		}

		return () => gallery.destroy();
	} );

	return (
		<div className="cld-gallery-settings-container">
			<div className="cld-gallery-settings">
				<div className="interface-interface-skeleton__sidebar cld-gallery-settings__column">
					<div className="interface-complementary-area edit-post-sidebar">
						<div className="components-panel">
							<div className="block-editor-block-inspector">
								<GalleryControls
									attributes={ statefulAttrs }
									setAttributes={ setAttributes }
								/>
							</div>
						</div>
					</div>
				</div>
				<div className="gallery-preview cld-gallery-settings__column"></div>
			</div>
		</div>
	);
};

render(
	<StatefulGalleryControls />,
	document.getElementById( 'app_gallery_config' )
);
