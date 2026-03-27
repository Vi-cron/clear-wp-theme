// Initialize Swiper Slider
document.addEventListener('DOMContentLoaded', function() {
    // Swiper initialization
    const swiper = new Swiper('.hero-slider', {
        loop: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        lazy: {
            loadPrevNext: true,
        },
    });
    
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const primaryMenu = document.querySelector('.primary-menu');
    
    if (menuToggle && primaryMenu) {
        menuToggle.addEventListener('click', function() {
            primaryMenu.classList.toggle('active');
        });
    }
    /*
    // AJAX Add to Cart
    const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            
            fetch(wc_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'woocommerce_add_to_cart',
                    'product_id': productId,
                    'quantity': 1,
                    'nonce': wc_ajax.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                } else {
                    // Update cart count
                    fetch(wc_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'action': 'update_cart_count',
                            'nonce': wc_ajax.nonce
                        })
                    })
                    .then(response => response.text())
                    .then(count => {
                        document.querySelector('.cart-count').textContent = count;
                    });
                }
            });
        });
    });
    */
	
    // Wishlist functionality (if TI WooCommerce Wishlist is active)
    const wishlistButtons = document.querySelectorAll('.wishlist-btn');
    
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            
            // Add your wishlist AJAX logic here
            // This depends on the wishlist plugin you're using
            alert('Добавлено в избранное!');
        });
    });
});

// Lazy loading for images
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    
    if ('loading' in HTMLImageElement.prototype) {
        // Browser supports native lazy loading
        images.forEach(img => {
            img.setAttribute('loading', 'lazy');
        });
    } else {
        // Fallback for browsers that don't support lazy loading
        const lazyLoad = function() {
            const scrollTop = window.pageYOffset;
            
            images.forEach(img => {
                if (img.offsetTop < window.innerHeight + scrollTop) {
                    img.setAttribute('src', img.dataset.src);
                    img.classList.add('loaded');
                }
            });
            
            if (images.length === 0) {
                window.removeEventListener('scroll', lazyLoad);
                window.removeEventListener('resize', lazyLoad);
            }
        };
        
        window.addEventListener('scroll', lazyLoad);
        window.addEventListener('resize', lazyLoad);
        lazyLoad();
    }
});