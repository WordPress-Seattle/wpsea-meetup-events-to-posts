<?php if( $field[ 'label_for' ] == self::PREFIX . 'meetup_api_key' ) : ?>
	
	<input id="<?php echo esc_attr( self::PREFIX .'meetup_api_key' ); ?>" name="<?php echo esc_attr( self::PREFIX .'settings[meetup_api_key]' ); ?>" class="regular-text" value="<?php echo esc_attr( $this->settings[ 'meetup_api_key' ] ); ?>" />
	<span class="description"> You can <a href="http://www.meetup.com/meetup_api/key/">obtain an API key</a> from Meetup.com</span>

<?php endif; ?>

<?php if( $field[ 'label_for' ] == self::PREFIX . 'meetup_group_url_name' ) : ?>
	
	<input id="<?php echo esc_attr( self::PREFIX .'meetup_group_url_name' ); ?>" name="<?php echo esc_attr( self::PREFIX .'settings[meetup_group_url_name]' ); ?>" class="regular-text" value="<?php echo esc_attr( $this->settings[ 'meetup_group_url_name' ] ); ?>" />
	<span class="description"> The group slug used in meetup.com URLs. For example, if the URL of your Meetup.com page is <code>http://www.meetup.com/SeattleWordPressMeetup/</code> then you would enter <code>SeattleWordPressMeetup</code> in this field.</span>

<?php endif; ?>
