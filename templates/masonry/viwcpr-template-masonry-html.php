<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $my_comments ) || ! is_array( $my_comments ) || empty( $settings ) ) {
	return;
}

/**
 * START: V21 - Truncation Fix
 *
 * This version improves CSS text truncation for a cleaner layout.
 * 1. Title is limited to a single line with an ellipsis.
 * 2. Content is robustly limited to ~5 lines with an ellipsis, handling long words correctly.
 * 3. Includes all previous fixes (swipe direction, stability, etc.).
 */

// 1. Set up all necessary variables from the original template at the top.
$prefix = $is_shortcode ? 'shortcode_' : '';
$prefix_class = $is_shortcode ? 'shortcode-' : '';
global $product;

$updated_cmt_meta = get_option( 'wcpr_comment_meta_updated' );
$user = wp_get_current_user();
if ( $user && ! empty( $user->ID ) ) {
	$vote_info = $user->ID;
	if ( $settings->get_params( 'review_edit_enable' ) ) {
		$user_id = $vote_info;
	}
} else {
	$vote_info = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_the_user_ip();
}

// 2. Add Custom CSS.
?>
<style>
    /* --- Grid Uniformity & Clickable Cards --- */
    .wcpr-grid { display: grid; grid-template-columns: repeat(var(--wcpr-grid-columns, 3), 1fr); gap: 15px; }
    .wcpr-grid-item {
        display: none; /* Hidden by default, JS will show the first batch */
        cursor: pointer; background: #fff; border-radius: 8px;
        overflow: hidden; border: 1px solid #eee; transition: transform 0.2s, box-shadow 0.2s;
    }
    .wcpr-grid-item:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
    .wcpr-grid-item .<?php echo esc_attr( $prefix_class ); ?>wcpr-content { display: flex; flex-direction: column; width: 100%; padding: 15px; }
    .wcpr-grid-item .<?php echo esc_attr( $prefix_class ); ?>review-content-container { flex-grow: 1; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .wcpr-grid-item.is-visible { display: flex; animation: fadeIn 0.5s ease-out forwards; }


    /* --- "Load More" Button Styles --- */
    .wcpr-load-more-container { text-align: center; margin-top: 30px; }
    .wcpr-load-more-btn {
        background-color: #f3f4f6; color: #374151; font-weight: 600;
        padding: 12px 30px; border-radius: 8px; border: 1px solid #e5e7eb;
        cursor: pointer; transition: background-color 0.2s, color 0.2s;
    }
    .wcpr-load-more-btn:hover { background-color: #e5e7eb; color: #1f2937; }


    /* --- CSS Truncation for Title and Content --- */
    .wcpr-grid-item .<?php echo esc_attr( $prefix_class ); ?>wcpr-review-title {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .wcpr-grid-item .<?php echo esc_attr( $prefix_class ); ?>wcpr-review-content {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 5; /* Approx. 120 characters fits well in 5 lines */
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 8px;
        
        /* FIX: Added properties for robust truncation */
        line-height: 1.5em; /* Controls spacing between lines */
        max-height: calc(1.5em * 5); /* Enforces the height for 5 lines */
        word-break: break-word; /* Crucial for long text without spaces */
    }

    /* --- Grid Image & Placeholder --- */
    .wcpr-grid-item .reviews-images-wrap-right { height: 150px; width: 100%; margin-bottom: 15px; }
    .wcpr-grid-item .reviews-images-wrap-right .reviews-images { width: 100% !important; height: 100% !important; object-fit: cover; border-radius: 4px; }
    .<?php echo esc_attr( $prefix_class ); ?>wcpr-image-placeholder {
        height: 150px; width: 100%; background-color: #f7f7f7; border-radius: 4px; display: flex;
        align-items: center; justify-content: center; color: #d0d0d0; margin-bottom: 15px;
    }
    .<?php echo esc_attr( $prefix_class ); ?>wcpr-image-placeholder::before { content: '📷'; font-size: 2.5em; opacity: 0.7; }
    
    /* --- "All Images" Slider with Arrows --- */
    .wcpr-all-reviews-gallery { position: relative; margin-bottom: 25px; padding-bottom: 10px; }
    .wcpr-all-reviews-gallery h3 { font-size: 1.5em; margin-bottom: 15px; }
    .wcpr-gallery-slider-container { display: flex; overflow-x: auto; gap: 15px; padding: 5px 10px; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; -ms-overflow-style: none; -webkit-overflow-scrolling: touch; cursor: grab; user-select: none; }
    .wcpr-gallery-slider-container.active-drag { cursor: grabbing; }
    .wcpr-gallery-slider-container::-webkit-scrollbar { display: none; }
    .wcpr-gallery-slider-image { width: 100px; height: 100px; object-fit: cover; cursor: pointer; border-radius: 8px; flex-shrink: 0; scroll-snap-align: start; }
    
    .wcpr-slider-arrow {
        position: absolute; top: 55%; transform: translateY(-50%); z-index: 10;
        width: 40px; height: 40px; border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        border: none; cursor: pointer; font-size: 20px; color: #333;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s, transform 0.3s;
    }
    .wcpr-all-reviews-gallery:hover .wcpr-slider-arrow { opacity: 1; }
    .wcpr-slider-arrow:hover { transform: translateY(-50%) scale(1.1); }
    .wcpr-slider-arrow.prev { left: 10px; }
    .wcpr-slider-arrow.next { right: 10px; }
    @media (max-width: 768px) { .wcpr-slider-arrow { display: none; } }
    
    /* --- "Review Detail" Popup --- */
    .wcpr-detail-modal { display: none; position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; overflow-y: auto; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 20px 0; }
    .wcpr-detail-modal-content { position: relative; margin: 20px auto; width: 95%; max-width: 800px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); animation: wcpr-modal-zoom 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); display: flex; flex-direction: column; overflow: hidden; }
    @keyframes wcpr-modal-zoom { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
    .wcpr-detail-modal-close { position: absolute; top: 15px; right: 15px; width: 30px; height: 30px; background: rgba(255, 255, 255, 0.8); border-radius: 50%; color: #555; font-size: 24px; font-weight: bold; cursor: pointer; z-index: 15; display: flex; align-items: center; justify-content: center; line-height: 1; padding-bottom: 3px; }
    .wcpr-detail-modal-image-gallery { position: relative; width: 100%; padding-top: 60%; background-color: #f0f0f0; }
    .wcpr-detail-modal-image { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; }
    .wcpr-modal-img-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 12; width: 35px; height: 35px; border-radius: 50%; background: rgba(0,0,0,0.4); border: none; color: white; font-size: 18px; cursor: pointer; display: none; }
    .wcpr-modal-img-arrow.prev { left: 15px; } .wcpr-modal-img-arrow.next { right: 15px; }
    .wcpr-modal-img-counter { position: absolute; bottom: 15px; right: 15px; background-color: rgba(0,0,0,0.6); color: white; padding: 5px 12px; border-radius: 15px; font-size: 0.9em; font-weight: 500; z-index: 13; display: none; }
    .wcpr-detail-modal-body { padding: 25px; flex-grow: 1; }
    .wcpr-modal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
    .wcpr-modal-author-info .wcpr-comment-author { font-weight: bold; font-size: 1.1em; }
    .wcpr-modal-meta-info { text-align: right; }
    .wcpr-modal-meta-info .wcpr-review-date { color: #888; font-size: 0.9em; }
    .wcpr-modal-meta-info .star-rating { margin-top: 5px; }
    .wcpr-modal-content-text { color: #333; line-height: 1.6; white-space: pre-wrap; overflow-wrap: break-word; word-break: break-all; }
    .wcpr-detail-modal-footer { padding: 15px 25px; background-color: #f9f9f9; border-top: 1px solid #eee; }
    .wcpr-modal-main-nav { position: fixed; top: 50%; width: 100%; left: 0; display: flex; justify-content: space-between; transform: translateY(-50%); pointer-events: none; z-index: 100000; }
    .wcpr-modal-main-nav-btn { pointer-events: all; width: 45px; height: 45px; border-radius: 50%; background: rgba(30,30,30,0.5); box-shadow: 0 4px 15px rgba(0,0,0,0.2); border: none; color: white; font-size: 22px; cursor: pointer; transition: transform 0.2s, background-color 0.2s; margin: 0 20px; }
    .wcpr-modal-main-nav-btn:hover { background-color: rgba(0,0,0,0.7); transform: scale(1.1); }
    .wcpr-modal-main-nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
<?php
// 3. Prepare data for sliders and popup.
$gallery_images = [];
$all_reviews_data = [];
$non_parent_comments = array_filter($my_comments, function($c) { return !$c->comment_parent; });
$non_parent_comments = array_values($non_parent_comments);

foreach ( $non_parent_comments as $i => $comment ) {
	$img_post_ids = get_comment_meta( $comment->comment_ID, 'reviews-images', true );
	if ( is_array( $img_post_ids ) && ! empty( $img_post_ids ) && !empty(array_filter($img_post_ids)) ) {
		$first_img_id = $img_post_ids[0];
        if ($thumb_url = (!villatheme_is_url($first_img_id) ? wp_get_attachment_image_url($first_img_id, 'thumbnail') : $first_img_id)) {
            $gallery_images[] = [ 'thumb_url' => $thumb_url, 'review_index' => $i ];
        }
	}
    ob_start(); comment_author( $comment ); $author = ob_get_clean();
    $review_date_format = $settings->get_params( 'photo', 'custom_review_date_format' ) ?: VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_date_format();
    $date = get_comment_date( $review_date_format, $comment );
    $rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
    $rating_html = $rating > 0 ? wc_get_rating_html( $rating ) : '';
    $is_verified = 'yes' === get_option( 'woocommerce_review_rating_verification_label' ) && 1 == get_comment_meta( $comment->comment_ID, 'verified', true );
    $content_text = $comment->comment_content;
    $review_images = [];
    if ( is_array( $img_post_ids ) && ! empty( $img_post_ids ) ) {
        foreach ($img_post_ids as $img_id) {
            if ($full_url = (!villatheme_is_url($img_id) ? wp_get_attachment_image_url($img_id, 'full') : $img_id)) $review_images[] = $full_url;
        }
    }
    ob_start();
    if ($settings->get_params('photo', 'helpful_button_enable') && 1 == $comment->comment_approved) {
        $class = 'wcpr-comment-helpful-button-container';
        $up_votes_raw = get_comment_meta( $comment->comment_ID, 'wcpr_vote_up', false );
        if ( in_array( $vote_info, $up_votes_raw ) ) $class .= ' wcpr-comment-helpful-button-voted-up';
        ?>
        <div class="<?php echo esc_attr($class) ?>" data-comment_id="<?php echo esc_attr($comment->comment_ID) ?>">
            <div class="wcpr-comment-helpful-button-voting-overlay"></div>
            <div class="wcpr-comment-helpful-button-vote-container">
            <?php if ($label = $settings->get_params('photo', 'helpful_button_title', VI_WOOCOMMERCE_PHOTO_REVIEWS_Frontend_Frontend::get_language())): ?>
                <span class="wcpr-comment-helpful-button-label"><?php echo wp_kses_post($label) ?></span>
            <?php endif; ?>
            <span class="wcpr-comment-helpful-button-up-vote-count"><?php echo esc_html(absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_up_count', true))) ?></span>
            <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-up-vote woocommerce-photo-reviews-vote-like"></span>
            <span class="wcpr-comment-helpful-button wcpr-comment-helpful-button-down-vote woocommerce-photo-reviews-vote-like"></span>
            <span class="wcpr-comment-helpful-button-down-vote-count"><?php echo esc_html(absint(get_comment_meta($comment->comment_ID, 'wcpr_vote_down_count', true))) ?></span>
            </div>
        </div>
        <?php
    }
    $helpful_html = ob_get_clean();
    $all_reviews_data[] = [
        'author_html' => $author, 'date' => $date, 'rating_html' => $rating_html, 'is_verified' => $is_verified,
        'content_text' => $content_text, 'helpful_html' => $helpful_html, 'images' => $review_images
    ];
}

// 4. Render the gallery and Popup HTML.
if ( ! empty( $gallery_images ) ) : ?>
    <div class="wcpr-all-reviews-gallery">
        <h3><?php esc_html_e( 'تصاویر خریداران', 'woocommerce-photo-reviews' ); ?></h3>
        <div class="wcpr-gallery-slider-container">
			<?php foreach ( $gallery_images as $image_data ) : ?>
                <img src="<?php echo esc_url( $image_data['thumb_url'] ); ?>" class="wcpr-gallery-slider-image" alt="<?php esc_attr_e( 'Customer review image', 'woocommerce-photo-reviews' ); ?>" data-review-index="<?php echo esc_attr($image_data['review_index']); ?>">
			<?php endforeach; ?>
        </div>
        <button class="wcpr-slider-arrow prev">&gt;</button>
        <button class="wcpr-slider-arrow next">&lt;</button>
    </div>
<?php endif; ?>
<div id="wcpr-detail-modal" class="wcpr-detail-modal">
    <div class="wcpr-detail-modal-content"><span id="wcpr-detail-modal-close" class="wcpr-detail-modal-close">&times;</span>
        <div id="wcpr-modal-image-gallery" class="wcpr-detail-modal-image-gallery">
            <img id="wcpr-modal-image" src="" class="wcpr-detail-modal-image"><button id="wcpr-modal-img-prev" class="wcpr-modal-img-arrow prev">&gt;</button><button id="wcpr-modal-img-next" class="wcpr-modal-img-arrow next">&lt;</button><div id="wcpr-modal-img-counter" class="wcpr-modal-img-counter"></div>
        </div>
        <div class="wcpr-detail-modal-body">
            <div class="wcpr-modal-header">
                <div class="wcpr-modal-author-info"><div id="wcpr-modal-author" class="wcpr-comment-author"></div><div id="wcpr-modal-verified"></div></div>
                <div class="wcpr-modal-meta-info"><div id="wcpr-modal-date" class="wcpr-review-date"></div><div id="wcpr-modal-rating"></div></div>
            </div>
            <div id="wcpr-modal-content-text" class="wcpr-modal-content-text"></div>
        </div>
        <div id="wcpr-modal-footer" class="wcpr-detail-modal-footer"></div>
    </div>
    <div class="wcpr-modal-main-nav"><button id="wcpr-modal-main-prev" class="wcpr-modal-main-nav-btn prev">&lt;</button><button id="wcpr-modal-main-next" class="wcpr-modal-main-nav-btn next">&gt;</button></div>
</div>
<?php
// 5. Render the Main Review Grid.
$return_product = $product;
$grid_tag_html = ! empty( $parent_tag_html ) && in_array( $parent_tag_html, [ 'ul', 'ol' ] ) ? 'li' : 'div';
printf('<div class="wcpr-grid-container"><%s class="wcpr-grid" style="--wcpr-grid-columns: %d;">', $grid_tag_html, esc_attr($cols ?? 3));
$review_title_enable = $settings->get_params( 'review_title_enable' );

foreach ( $non_parent_comments as $v_index => $v ) {
	$comment = $v;
	$product = $is_shortcode ? wc_get_product( $comment->comment_post_ID ) : $return_product;
	if ( $product ) {
        printf( '<div class="%swcpr-grid-item" data-review-index="%d"><div class="%swcpr-content">', $prefix_class, $v_index, $prefix_class );
		$img_post_ids = get_comment_meta( $v->comment_ID, 'reviews-images', true );
		if ( is_array( $img_post_ids ) && !empty( $img_post_ids ) && !empty(array_filter($img_post_ids)) ) {
			$first_ele = $img_post_ids[0];
            $grid_img_src = !villatheme_is_url($first_ele) ? wp_get_attachment_image_url($first_ele, 'medium') : $first_ele;
            ?>
            <div class="reviews-images-wrap-right"><img class="reviews-images" loading="lazy" src="<?php echo esc_url($grid_img_src); ?>" alt="<?php esc_attr_e('Review image', 'woocommerce-photo-reviews'); ?>"></div>
            <?php
		} else {
			printf( '<div class="%swcpr-image-placeholder"></div>', esc_attr( $prefix_class ) );
		}
		printf( '<div class="%sreview-content-container">', esc_attr( $prefix_class ) );
        if ( '0' === $v->comment_approved ) {
			printf( '<p class="meta"><em class="woocommerce-review__awaiting-approval">%s</em></p>', esc_html__( 'Your review is awaiting approval', 'woocommerce-photo-reviews' ) );
        } else {
            $rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) ); ?>
            <div class="<?php echo esc_attr( $prefix_class ); ?>review-content-container-top">
                <div class="<?php echo esc_attr( $prefix_class . 'wcpr-comment-author' ); ?>"><?php comment_author( $comment ); ?></div>
                <div class="wcpr-review-rating"><?php if ( $rating > 0 ) echo wc_get_rating_html( $rating ); ?></div>
            </div>
            <?php
        }
        if ( $review_title_enable && ( $review_title = get_comment_meta( $comment->comment_ID, 'wcpr_review_title', true ) ) ) {
			printf( '<div class="%swcpr-review-title">%s</div>', esc_attr( $prefix_class ), esc_html( $review_title ) );
		}
		?>
        <div class="<?php echo esc_attr( $prefix_class ); ?>wcpr-review-content"><?php echo esc_html( strip_tags( $comment->comment_content ) ); ?></div>
		<?php
        printf( '</div></div></div>' );
	}
}
$product = $return_product;
printf('</%s></div>', $grid_tag_html);

if ( count($non_parent_comments) > 8 ) {
    ?>
    <div class="wcpr-load-more-container">
        <button id="wcpr-load-more-btn" class="wcpr-load-more-btn">مشاهده بیشتر</button>
    </div>
    <?php
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Data and DOM Setup ---
    const allReviewsData = <?php echo wp_json_encode($all_reviews_data); ?>;
    
    // ROBUSTNESS FIX: Check if data is a valid array. If not, exit gracefully to prevent a crash.
    if (!Array.isArray(allReviewsData) || !allReviewsData.length) {
        console.warn('WooCommerce Photo Reviews: No review data found or data is invalid.');
        return;
    }

    // Create an array of indices for only those reviews that have images.
    const reviewsWithImagesIndices = allReviewsData
        .map((review, index) => (review.images && review.images.length > 0 ? index : -1))
        .filter(index => index !== -1);

    const sliderContainer = document.querySelector('.wcpr-gallery-slider-container');
    const modal = document.getElementById('wcpr-detail-modal');
    const allReviewItems = document.querySelectorAll('.wcpr-grid > .wcpr-grid-item');

    // --- "Load More" Logic: Make initial reviews visible ---
    const reviewsToShow = 8;
    for (let i = 0; i < reviewsToShow && i < allReviewItems.length; i++) {
        allReviewItems[i].classList.add('is-visible');
    }
    
    const loadMoreBtn = document.getElementById('wcpr-load-more-btn');
    let currentlyVisible = reviewsToShow;
    if (loadMoreBtn) {
        if (allReviewItems.length <= reviewsToShow) {
            loadMoreBtn.style.display = 'none';
        }
        loadMoreBtn.addEventListener('click', function() {
            this.disabled = true; this.textContent = '...';
            setTimeout(() => {
                const nextLimit = currentlyVisible + reviewsToShow;
                for (let i = currentlyVisible; i < nextLimit && i < allReviewItems.length; i++) {
                    allReviewItems[i].classList.add('is-visible');
                }
                currentlyVisible = nextLimit;
                if (currentlyVisible >= allReviewItems.length) { this.style.display = 'none'; }
                this.disabled = false; this.textContent = 'مشاهده بیشتر';
            }, 250);
        });
    }

    // --- Top "All Images" Slider Logic (with Click + Drag) ---
    if (sliderContainer) {
        const prevArrow = document.querySelector('.wcpr-slider-arrow.prev');
        const nextArrow = document.querySelector('.wcpr-slider-arrow.next');
        const scrollAmount = sliderContainer.querySelector('.wcpr-gallery-slider-image')?.offsetWidth + 15 || 115;
        
        if(prevArrow) prevArrow.addEventListener('click', () => sliderContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' }));
        if(nextArrow) nextArrow.addEventListener('click', () => sliderContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' }));

        let isDown = false, startX, scrollLeft;
        sliderContainer.addEventListener('mousedown', e => { isDown = true; sliderContainer.classList.add('active-drag'); startX = e.pageX; scrollLeft = sliderContainer.scrollLeft; });
        sliderContainer.addEventListener('mouseleave', () => { isDown = false; sliderContainer.classList.remove('active-drag'); });
        sliderContainer.addEventListener('mouseup', e => {
            isDown = false;
            sliderContainer.classList.remove('active-drag');
            if (Math.abs(e.pageX - startX) < 5) { // Click vs drag threshold
                const clickedImage = e.target.closest('.wcpr-gallery-slider-image');
                if (clickedImage) {
                    isNavigatingFromGallery = true; // Set navigation context
                    openModal(parseInt(clickedImage.dataset.reviewIndex, 10));
                }
            }
        });
        sliderContainer.addEventListener('mousemove', e => { if (!isDown) return; e.preventDefault(); const walk = (e.pageX - startX) * 2; sliderContainer.scrollLeft = scrollLeft - walk; });
    }

    // --- "Review Detail" Popup Logic ---
    if (!modal) return; // Exit if modal HTML doesn't exist
    
    const modalContent = document.querySelector('.wcpr-detail-modal-content'),
          modalImageGallery = document.getElementById('wcpr-modal-image-gallery'), modalImage = document.getElementById('wcpr-modal-image'),
          modalImgPrev = document.getElementById('wcpr-modal-img-next'), modalImgNext = document.getElementById('wcpr-modal-img-prev'),
          modalAuthor = document.getElementById('wcpr-modal-author'), modalVerified = document.getElementById('wcpr-modal-verified'),
          modalDate = document.getElementById('wcpr-modal-date'), modalRating = document.getElementById('wcpr-modal-rating'),
          modalContentText = document.getElementById('wcpr-modal-content-text'), modalFooter = document.getElementById('wcpr-modal-footer'),
          mainPrevBtn = document.getElementById('wcpr-modal-main-prev'), mainNextBtn = document.getElementById('wcpr-modal-main-next'),
          modalImgCounter = document.getElementById('wcpr-modal-img-counter');

    let currentReviewIndex = -1, currentImageIndex = 0, touchStartX = 0;
    let isNavigatingFromGallery = false; // State variable for navigation context

    const populateModal = (reviewIndex) => {
        const data = allReviewsData[reviewIndex]; if (!data) return;
        currentReviewIndex = reviewIndex;
        if(modalAuthor) modalAuthor.innerHTML = data.author_html;
        if(modalDate) modalDate.textContent = data.date;
        if(modalRating) modalRating.innerHTML = data.rating_html;
        if(modalContentText) modalContentText.textContent = data.content_text;
        if(modalFooter) modalFooter.innerHTML = data.helpful_html;
        if(modalVerified) modalVerified.innerHTML = data.is_verified ? '<em class="woocommerce-review__verified verified"><?php esc_html_e("Verified purchase", "woocommerce"); ?></em>' : '';
        
        currentImageIndex = 0;
        if (modalImageGallery) modalImageGallery.style.display = (data.images && data.images.length > 0) ? 'block' : 'none';
        if (data.images && data.images.length > 0) updateModalImage();
        
        if (isNavigatingFromGallery) {
            const currentIndexInGallery = reviewsWithImagesIndices.indexOf(currentReviewIndex);
            if(mainPrevBtn) mainPrevBtn.disabled = (currentIndexInGallery === 0);
            if(mainNextBtn) mainNextBtn.disabled = (currentIndexInGallery === reviewsWithImagesIndices.length - 1);
        } else {
            if(mainPrevBtn) mainPrevBtn.disabled = (currentReviewIndex === 0);
            if(mainNextBtn) mainNextBtn.disabled = (currentReviewIndex === allReviewsData.length - 1);
        }
    };

    const updateModalImage = () => {
        const reviewImages = allReviewsData[currentReviewIndex].images;
        if(modalImage) modalImage.src = reviewImages[currentImageIndex];
        const showExtras = reviewImages.length > 1;
        if(modalImgPrev) modalImgPrev.style.display = showExtras ? 'block' : 'none';
        if(modalImgNext) modalImgNext.style.display = showExtras ? 'block' : 'none';
        if(modalImgCounter) modalImgCounter.style.display = showExtras ? 'block' : 'none';
        if(modalImgPrev) modalImgPrev.disabled = (currentImageIndex === 0);
        if(modalImgNext) modalImgNext.disabled = (currentImageIndex === reviewImages.length - 1);
        if (showExtras && modalImgCounter) { modalImgCounter.textContent = `${reviewImages.length} / ${currentImageIndex + 1}`; }
    };

    const openModal = (reviewIndex) => { populateModal(reviewIndex); modal.style.display = 'block'; document.body.style.overflow = 'hidden'; };
    const closeModal = () => { modal.style.display = 'none'; document.body.style.overflow = ''; };

    allReviewItems.forEach(item => item.addEventListener('click', () => { isNavigatingFromGallery = false; openModal(parseInt(item.dataset.reviewIndex, 10)) }));
    
    const closeBtn = document.getElementById('wcpr-detail-modal-close');
    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => e.target === modal && closeModal());

    if(mainPrevBtn) mainPrevBtn.addEventListener('click', () => {
        if (isNavigatingFromGallery) {
            const idx = reviewsWithImagesIndices.indexOf(currentReviewIndex);
            if (idx > 0) populateModal(reviewsWithImagesIndices[idx - 1]);
        } else {
            if (currentReviewIndex > 0) populateModal(currentReviewIndex - 1);
        }
    });
    if(mainNextBtn) mainNextBtn.addEventListener('click', () => {
        if (isNavigatingFromGallery) {
            const idx = reviewsWithImagesIndices.indexOf(currentReviewIndex);
            if (idx < reviewsWithImagesIndices.length - 1) populateModal(reviewsWithImagesIndices[idx + 1]);
        } else {
            if (currentReviewIndex < allReviewsData.length - 1) populateModal(currentReviewIndex + 1);
        }
    });

    if(modalImgPrev) modalImgPrev.addEventListener('click', e => { e.stopPropagation(); if (currentImageIndex > 0) { currentImageIndex--; updateModalImage(); } });
    if(modalImgNext) modalImgNext.addEventListener('click', e => { e.stopPropagation(); const imgs = allReviewsData[currentReviewIndex].images; if (currentImageIndex < imgs.length - 1) { currentImageIndex++; updateModalImage(); } });
    
    document.addEventListener('keydown', e => { if (modal.style.display !== 'block') return; if (e.key === 'Escape') closeModal(); if (e.key === 'ArrowLeft' && mainPrevBtn) mainPrevBtn.click(); if (e.key === 'ArrowRight' && mainNextBtn) mainNextBtn.click(); });

    // --- All Swipe Logic ---
    if (modalImageGallery) {
        modalImageGallery.addEventListener('touchstart', e => { if (allReviewsData[currentReviewIndex]?.images.length > 1) { touchStartX = e.changedTouches[0].screenX; } }, {passive: true});
        modalImageGallery.addEventListener('touchend', e => {
            if (allReviewsData[currentReviewIndex]?.images.length <= 1) return;
            const swipeDiff = e.changedTouches[0].screenX - touchStartX;
            // SWIPE DIRECTION FIX: Inverted the logic. Swipe right -> next, Swipe left -> prev.
            if (swipeDiff < -50 && modalImgPrev) modalImgPrev.click(); // Finger moves left, go to PREV
            else if (swipeDiff > 50 && modalImgNext) modalImgNext.click(); // Finger moves right, go to NEXT
        });
    }

    if (modalContent) {
        let reviewSwipeStartX = 0;
        modalContent.addEventListener('touchstart', e => { if (!e.target.closest('#wcpr-modal-image-gallery')) { reviewSwipeStartX = e.changedTouches[0].screenX; } }, {passive: true});
        modalContent.addEventListener('touchend', e => {
            if (e.target.closest('#wcpr-modal-image-gallery')) return;
            const swipeDiff = e.changedTouches[0].screenX - reviewSwipeStartX;
            // SWIPE DIRECTION FIX: Inverted the logic. Swipe right -> next, Swipe left -> prev.
            if (swipeDiff < -50 && mainPrevBtn) mainPrevBtn.click(); // Finger moves left, go to PREV
            else if (swipeDiff > 50 && mainNextBtn) mainNextBtn.click(); // Finger moves right, go to NEXT
        });
    }
});
</script>