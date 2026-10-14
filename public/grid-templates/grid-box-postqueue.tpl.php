<?php

defined( 'ABSPATH' ) || exit;
/**
 * @author Palasthotel <webmaster@palasthotel.de>
 * @copyright Copyright (c) 2014, Palasthotel
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 * @package Palasthotel\Postqueue
 *
 * @var $this grid_postqueue_box
 * @var $content array $this->content
 */

$postqueue = $content->postqueue;
$viewmode = (!empty($content->viewmode))? $content->viewmode: "";
$offset = (!empty($content->offset))? $content->offset: 0;
$limit = (!empty($content->limit))? $content->limit: -1;

?>
<div class="grid-box<?php echo esc_attr( ( $this->style ) ? " " . $this->style . " " : " " ); echo esc_attr( implode( " ", $this->classes ) ); ?>">
	<?php
	if ($this->title!=""){
		
		if ($this->titleurl !=""){
			?>
			<h2 class="grid-box-title"><a href="<?php echo esc_url( $this->titleurl ); ?>"><?php echo wp_kses_post( $this->title ); ?></a></h2>
		<?php }else{?>
			<h2 class="grid-box-title"><?php echo wp_kses_post( $this->title ); ?></h2>
		<?php }?>
	<?php }?>
	
	<?php if($this->prolog != "") { ?>
		<div class="grid-box-prolog">
			<?php echo wp_kses_post( $this->prolog ); ?>
		</div>
	<?php } ?>
	
	<?php
	echo do_shortcode("[postqueue slug='{$postqueue}' viewmode='{$viewmode}' offset='{$offset}' limit='{$limit}']");
	?>
	
	<?php if($this->epilog != ""){ ?>
		<div class="grid-box-epilog">
			<?php echo wp_kses_post( $this->epilog ); ?>
		</div>
	<?php } ?>
	
	<?php
	if ($this->readmore!=""){?>
		<a href="<?php echo esc_url( $this->readmoreurl ); ?>" class="grid-box-readmore-link"><?php echo wp_kses_post( $this->readmore ); ?></a>
	<?php }?>

</div>
