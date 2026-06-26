<?php
/**
 * Single archive video card.
 *
 * Override: copy to yourtheme/drtalks-videos/content-archive-card.php
 *
 * Available variables:
 *
 * @var int    $post_id
 * @var string $title
 * @var string $permalink
 * @var string $thumbnail
 * @var string $duration      Pre-formatted MM:SS string (may be empty).
 * @var string $expert_name
 * @var string $expert_photo  URL (may be empty).
 * @var array  $guests        Each item: ['name'=>string,'photo_url'=>string,...].
 *
 * @package DrTalksVideos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build a flat list of all people: host first, then guests.
$people = [];
if ( ! empty( $expert_name ) ) {
	$people[] = [
		'name'  => $expert_name,
		'photo' => $expert_photo,
	];
}
foreach ( (array) $guests as $guest ) {
	$people[] = [
		'name'  => isset( $guest['name'] )      ? (string) $guest['name']      : '',
		'photo' => isset( $guest['photo_url'] ) ? (string) $guest['photo_url'] : '',
	];
}

// Avatar stack: show up to 4, badge for the rest.
$max_avatars   = 4;
$visible       = array_slice( $people, 0, $max_avatars );
$extra_count   = count( $people ) - count( $visible );

// Combined names label: "John Doe & Jane Doe & Dr. Smith"
$all_names = array_filter( array_column( $people, 'name' ) );
if ( count( $all_names ) === 1 ) {
	$names_label = reset( $all_names );
} elseif ( count( $all_names ) === 2 ) {
	$names_label = implode( ' & ', $all_names );
} elseif ( count( $all_names ) > 2 ) {
	$first_two   = array_slice( $all_names, 0, 2 );
	$names_label = implode( ' & ', $first_two ) . ' & ' . ( count( $all_names ) - 2 ) . ' more';
} else {
	$names_label = '';
}
?>
<article class="drtalks-video-card">
	<a href="<?php echo esc_url( $permalink ); ?>" class="drtalks-card-link">

		<div class="drtalks-card-thumbnail">
			<?php if ( $thumbnail ) : ?>
			<img src="<?php echo esc_url( $thumbnail ); ?>"
				alt="<?php echo esc_attr( $title ); ?>"
				loading="lazy">
			<?php else : ?>
			<div class="drtalks-card-thumbnail-placeholder"></div>
			<?php endif; ?>
			<?php if ( $duration ) : ?>
			<span class="drtalks-card-duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
		</div>

		<div class="drtalks-card-body">
			<h2 class="drtalks-card-title"><?php echo esc_html( $title ); ?></h2>

			<?php if ( ! empty( $people ) ) : ?>
			<div class="drtalks-card-people">
				<div class="drtalks-card-avatars">
					<?php foreach ( $visible as $person ) :
						$pname  = $person['name'];
						$pphoto = $person['photo'];
					?>
					<?php if ( $pphoto ) : ?>
					<img class="drtalks-card-avatar"
						src="<?php echo esc_url( $pphoto ); ?>"
						alt="<?php echo esc_attr( $pname ); ?>"
						loading="lazy"
						title="<?php echo esc_attr( $pname ); ?>">
					<?php else : ?>
					<span class="drtalks-card-avatar drtalks-card-avatar--initials"
						aria-hidden="true"
						title="<?php echo esc_attr( $pname ); ?>">
						<?php echo esc_html( mb_strtoupper( mb_substr( $pname, 0, 1 ) ) ); ?>
					</span>
					<?php endif; ?>
					<?php endforeach; ?>
					<?php if ( $extra_count > 0 ) : ?>
					<span class="drtalks-card-avatar drtalks-card-avatar--more">+<?php echo (int) $extra_count; ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $names_label ) : ?>
				<span class="drtalks-card-people-names"><?php echo esc_html( $names_label ); ?></span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>

	</a>
</article>
