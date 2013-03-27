<?php if( $section[ 'id' ] == self::PREFIX . 'section-settings' ) : ?>
	
	<p>Enter the information below to enable pulling your Meetup.com events into <a href="<?php echo admin_url( 'edit.php?post_type=' . self::POST_TYPE_SLUG ); ?>">custom posts</a>. Events will be pulled once every hour.</p>
	
<?php endif; ?>