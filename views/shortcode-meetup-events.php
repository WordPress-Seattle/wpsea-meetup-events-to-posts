<?php if( $event_posts ) : ?>
	
	<?php foreach( $event_posts as $event_post ) : ?>
		<p>event post</p> 
		<!-- TODO: build out markup for details -->
		<!-- can use $event_post->metafieldname b/c 3.5. happy happy joy joy. -->
	<?php endforeach; ?>
	
<?php else : ?>
	
	<p>There aren't any upcoming Meetup events currently scheduled.</p>
		
<?php endif; ?>