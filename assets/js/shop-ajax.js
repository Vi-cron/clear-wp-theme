/**
 * AJAX Catalog with filters and infinite scroll
 * Единый обработчик для всех действий
 */

(function($) {
    'use strict';

    // State management
    const state = {
        ajaxRunning: false,
        currentPage: 1,
        maxPages: 1,
        isLoadingMore: false,
        filterTimeout: null,
        filters: {},
        initialLoad: true,
        totalProducts: 0,
        productsPerPage: 12
    };

    // DOM elements
    const elements = {
        $grid: $('#products-grid'),
        $paginationWrapper: $('#pagination-wrapper'),
        $shopContent: $('#shop-content'),
        $resultCount: $('.woocommerce-result-count')
    };

    /**
     * Get current category slug
     */
    function getCurrentCategory() {
        // Check if we're on a category page
        const $currentCat = $('.product-categories .current-cat');
        if ($currentCat.length) {
            const $link = $currentCat.find('a');
            if ($link.length) {
                const href = $link.attr('href');
                if (href) {
                    return href.split('/').filter(Boolean).pop();
                }
            }
        }
        
        // Check from URL
        const urlParams = new URLSearchParams(window.location.search);
        const catFromUrl = urlParams.get('product_cat');
        if (catFromUrl) {
            return catFromUrl;
        }
        
        // Check from body class
        const bodyClass = $('body').attr('class');
        if (bodyClass && bodyClass.includes('term-')) {
            const match = bodyClass.match(/term-(\d+)/);
            if (match) {
                const termId = match[1];
                const term = getTermById(termId);
                if (term) return term.slug;
            }
        }
        
        return null;
    }

    /**
     * Helper to get term by ID (simplified)
     */
    function getTermById(termId) {
        // This would need AJAX or preloaded data
        // For now, return null
        return null;
    }

    /**
     * Update result count display
     */
    function updateResultCount(start, end, total) {
        if (elements.$resultCount.length) {
            const text = elements.$resultCount.text();
            // Update the numbers while preserving the text structure
            const newText = text.replace(/\d+–\d+ из \d+/, `${start}–${end} из ${total}`);
            elements.$resultCount.text(newText);
        }
    }

    /**
     * Collect all active filters
     */
    function collectFilters() {
        const filters = {
            paged: state.currentPage
        };

        // Add current category if we're on category page
        const currentCategory = getCurrentCategory();
        if (currentCategory && !window.location.pathname.includes('/shop/')) {
            filters.product_cat = currentCategory;
        }

        // Price filter - standard inputs
        const $minPrice = $('input[name="min_price"]');
        const $maxPrice = $('input[name="max_price"]');
        
        if ($minPrice.length && $maxPrice.length) {
            const minPrice = $minPrice.val();
            const maxPrice = $maxPrice.val();
            if (minPrice && maxPrice && minPrice !== '' && maxPrice !== '') {
                filters.min_price = minPrice;
                filters.max_price = maxPrice;
            }
        }

        // Price slider from WooCommerce widget
        const $minPriceWc = $('.price_slider_amount #min_price');
        const $maxPriceWc = $('.price_slider_amount #max_price');
        
        if ($minPriceWc.length && $maxPriceWc.length) {
            const minPrice = $minPriceWc.val();
            const maxPrice = $maxPriceWc.val();
            if (minPrice && maxPrice && minPrice !== '' && maxPrice !== '') {
                filters.min_price = minPrice;
                filters.max_price = maxPrice;
            }
        }

        // Attribute filters
        $('.attribute-filter:checked').each(function() {
            const $this = $(this);
            const attributeName = $this.data('attribute-name');
            const paramName = 'filter_' + attributeName;
            
            if (!filters[paramName]) {
                filters[paramName] = [];
            }
            filters[paramName].push($this.val());
        });

        // Sorting
        const $orderby = $('.woocommerce-ordering select');
        if ($orderby.length && $orderby.val()) {
            filters.orderby = $orderby.val();
        }

        return filters;
    }

    /**
     * Show loading overlay/spinner with auto-scroll to visible area
     */
    function showLoading(append = false) {
        if (!append) {
            // Full grid loading
            elements.$grid.addClass('loading');
            // Add overlay if not exists
            if (!$('#grid-loading-overlay').length) {
                elements.$grid.append('<div id="grid-loading-overlay" class="grid-loading-overlay"><div class="loading-spinner"></div></div>');
            } else {
                $('#grid-loading-overlay').show();
            }
            
            // Scroll to make loading indicator visible
            const overlay = $('#grid-loading-overlay');
            if (overlay.length && !append) {
                const overlayTop = overlay.offset().top;
                const windowTop = $(window).scrollTop();
                const windowHeight = $(window).height();
                
                // If overlay is not visible, scroll to it
                if (overlayTop < windowTop || overlayTop > windowTop + windowHeight - 100) {
                    $('html, body').animate({
                        scrollTop: overlayTop - 100
                    }, 300);
                }
            }
        } else {
            // Infinite scroll loading indicator
            if (!$('#load-more-indicator').length) {
                elements.$grid.after('<div id="load-more-indicator" class="load-more-indicator active"><div class="loading-spinner"></div></div>');
            } else {
                $('#load-more-indicator').addClass('active').html('<div class="loading-spinner"></div>');
            }
            
            // Auto-scroll to make indicator visible
            setTimeout(() => {
                const indicator = $('#load-more-indicator');
                if (indicator.length) {
                    indicator[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            }, 100);
        }
    }

    /**
     * Hide loading overlay/spinner
     */
    function hideLoading(append = false) {
        if (!append) {
            elements.$grid.removeClass('loading');
            $('#grid-loading-overlay').remove();
        } else {
            $('#load-more-indicator').remove();
        }
    }

    /**
     * Update URL without page reload
     */
    function updateURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Clear existing filter params
        const filterKeys = ['min_price', 'max_price', 'orderby', 'paged', 'product_cat'];
        for (let key of urlParams.keys()) {
            if (key.startsWith('filter_') || filterKeys.includes(key)) {
                urlParams.delete(key);
            }
        }
        
        // Add current filters
        Object.keys(state.filters).forEach(key => {
            if (key !== 'paged' && state.filters[key] && state.filters[key] !== '') {
                if (Array.isArray(state.filters[key])) {
                    urlParams.set(key, state.filters[key].join(','));
                } else {
                    urlParams.set(key, state.filters[key]);
                }
            }
        });
        
        // Handle paged
        if (state.currentPage > 1) {
            urlParams.set('paged', state.currentPage);
        } else {
            urlParams.delete('paged');
        }
        
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.pushState({}, '', newUrl);
    }

    /**
     * Update active page in pagination and hide next/prev when needed
     */
    function updateActivePagination() {
        $('.pagination .page-numbers').removeClass('current');
        
        // Update current page
        $('.pagination .page-numbers').each(function() {
            const pageText = $(this).text();
            const pageNum = parseInt(pageText);
            if (!isNaN(pageNum) && pageNum === state.currentPage) {
                $(this).addClass('current');
            }
        });
        
        // Hide next button if on last page
        if (state.currentPage >= state.maxPages) {
            $('.pagination .next').hide();
        } else {
            $('.pagination .next').show();
        }
        
        // Hide prev button if on first page
        if (state.currentPage <= 1) {
            $('.pagination .prev').hide();
        } else {
            $('.pagination .prev').show();
        }
    }

    /**
     * Load products via AJAX
     * Returns a Promise for better async handling
     */
    function loadProducts(append = false) {
        return new Promise((resolve, reject) => {
            if (state.ajaxRunning) {
                reject('AJAX already running');
                return;
            }
            
            const filters = collectFilters();
            filters.paged = state.currentPage;
            state.filters = filters;
            
            state.ajaxRunning = true;
            
            // Show loading state
            showLoading(append);
            
            $.ajax({
                url: my_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_products_ajax',
                    filters: filters,
                    nonce: my_ajax.nonce
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        const html = response.data.html;
                        state.maxPages = response.data.max_pages;
                        state.totalProducts = response.data.total_products || 0;
                        state.productsPerPage = response.data.products_per_page || 12;
                        
                        // Calculate displayed range
                        const start = ((state.currentPage - 1) * state.productsPerPage) + 1;
                        const end = Math.min(state.currentPage * state.productsPerPage, state.totalProducts);
                        
                        if (append) {
                            elements.$grid.append(html);
                        } else {
                            elements.$grid.html(html);
                            updatePagination(response.data.pagination);
                        }
                        
                        // Update result count
                        if (state.totalProducts > 0) {
                            updateResultCount(start, end, state.totalProducts);
                        }
                        
                        // Update active page in pagination
                        updateActivePagination();
                        
                        // Update URL
                        updateURL();
                        
                        // Re-init WooCommerce add to cart and other scripts
                        $(document.body).trigger('wc_fragment_refresh');
                        
                        // Initialize any new product interactions
                        initProductInteractions();
                        
                        resolve(response.data);
                    } else {
                        console.error('AJAX error:', response);
                        if (!append) {
                            elements.$grid.html('<div class="no-products-found">Ошибка загрузки товаров. Пожалуйста, обновите страницу.</div>');
                        }
                        reject(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX request failed:', error);
                    if (!append) {
                        elements.$grid.html('<div class="no-products-found">Ошибка соединения. Пожалуйста, обновите страницу.</div>');
                    }
                    reject(error);
                },
                complete: function() {
                    state.ajaxRunning = false;
                    hideLoading(append);
                    
                    // Check infinite scroll after loading
                    if (state.currentPage < state.maxPages) {
                        setTimeout(checkInfiniteScroll, 100);
                    }
                }
            });
        });
    }

    /**
     * Update pagination display
     */
    function updatePagination(paginationHtml) {
        if (paginationHtml) {
            elements.$paginationWrapper.html(paginationHtml);
        } else {
            elements.$paginationWrapper.empty();
        }
    }

    /**
     * Apply filters (reset to page 1)
     */
    function applyFilters() {
        // Clear any pending timeout
        if (state.filterTimeout) {
            clearTimeout(state.filterTimeout);
        }
        
        state.filterTimeout = setTimeout(() => {
            // Don't apply if AJAX is already running
            if (state.ajaxRunning) {
                // Wait and try again
                state.filterTimeout = setTimeout(() => {
                    applyFilters();
                }, 100);
                return;
            }
            
            state.currentPage = 1;
            loadProducts(false).catch(error => {
                console.error('Failed to apply filters:', error);
            });
        }, 500);
    }

    /**
     * Load next page (for infinite scroll)
     */
    function loadNextPage() {
        // Prevent multiple simultaneous loads
        if (state.isLoadingMore) {
            return;
        }
        
        if (state.currentPage >= state.maxPages) {
            return;
        }
        
        if (state.ajaxRunning) {
            return;
        }
        
        state.isLoadingMore = true;
        const nextPage = state.currentPage + 1;
        
        // Temporarily set page to next
        const originalPage = state.currentPage;
        state.currentPage = nextPage;
        
        loadProducts(true)
            .then(() => {
                state.isLoadingMore = false;
            })
            .catch((error) => {
                console.error('Failed to load next page:', error);
                state.isLoadingMore = false;
                // Revert page number on error
                state.currentPage = originalPage;
            });
    }

    /**
     * Check if we need to load more (infinite scroll)
     */
    function checkInfiniteScroll() {
        // Don't check if we're already loading
        if (state.isLoadingMore) return;
        if (state.ajaxRunning) return;
        if (state.currentPage >= state.maxPages) return;
        
        // Check if user has scrolled near bottom
        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const documentHeight = $(document).height();
        const scrollPosition = scrollTop + windowHeight;
        const threshold = 300; // pixels from bottom
        
        if (scrollPosition >= documentHeight - threshold) {
            loadNextPage();
        }
    }

    /**
     * Initialize product interactions (add to cart, wishlist, etc.)
     */
    function initProductInteractions() {
        // Add to cart buttons
        $('.add-to-cart-btn').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = $(this).data('product-id');
            addToCart(productId);
        });
        
        // Wishlist buttons
        $('.wishlist-btn').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productId = $(this).data('product-id');
            toggleWishlist($(this), productId);
        });
    }

    /**
     * Add product to cart via AJAX
     */
    function addToCart(productId) {
        // Show loading state on button
        const $btn = $(`.add-to-cart-btn[data-product-id="${productId}"]`);
        const originalText = $btn.text();
        $btn.text('Добавляется...').prop('disabled', true);
        
        $.ajax({
            url: wc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'woocommerce_add_to_cart',
                product_id: productId,
                quantity: 1,
                nonce: wc_ajax.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    // Update cart count
                    $.ajax({
                        url: wc_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'update_cart_count',
                            nonce: wc_ajax.nonce
                        },
                        success: function(count) {
                            $('.cart-count').text(count);
                        }
                    });
                    
                    showNotification('Товар добавлен в корзину', 'success');
                    $btn.text('✓ В корзине');
                    setTimeout(() => {
                        $btn.text(originalText).prop('disabled', false);
                    }, 2000);
                } else {
                    showNotification('Ошибка добавления в корзину', 'error');
                    $btn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showNotification('Ошибка добавления в корзину', 'error');
                $btn.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Toggle wishlist
     */
    function toggleWishlist($btn, productId) {
        if (typeof window.tinv_wishlist !== 'undefined') {
            $btn.toggleClass('active');
            showNotification('Обновлено', 'info');
        } else {
            console.log('Wishlist functionality not available');
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        $('.notification').remove();
        
        const $notification = $('<div class="notification notification-' + type + '">' + message + '</div>');
        $('body').append($notification);
        $notification.fadeIn(300).delay(2000).fadeOut(300, function() {
            $(this).remove();
        });
    }

    /**
     * Handle pagination clicks
     */
    function handlePaginationClick(e) {
        e.preventDefault();
        const $this = $(this);
        
        if ($this.hasClass('current')) return;
        if (state.ajaxRunning) return;
        
        let page = null;
        
        if ($this.hasClass('next')) {
            if (state.currentPage < state.maxPages) {
                page = state.currentPage + 1;
            }
        } else if ($this.hasClass('prev')) {
            if (state.currentPage > 1) {
                page = state.currentPage - 1;
            }
        } else {
            const pageText = $this.text();
            if (!isNaN(pageText) && pageText !== '...') {
                page = parseInt(pageText);
            }
        }
        
        if (page && page !== state.currentPage) {
            state.currentPage = page;
            loadProducts(false).catch(error => {
                console.error('Failed to load page:', error);
            });
            
            // Scroll to top of products
            $('html, body').animate({
                scrollTop: elements.$shopContent.offset().top - 100
            }, 400);
        }
    }

    /**
     * Handle attribute filter change
     */
    function handleAttributeChange() {
        applyFilters();
    }

    /**
     * Handle sorting change
     */
    function handleSortingChange(e) {
        e.preventDefault();
        applyFilters();
    }

    /**
     * Handle price filter apply
     */
    function handlePriceFilter(e) {
        e.preventDefault();
        applyFilters();
    }

    /**
     * Handle clear filters
     */
    function handleClearFilters(e) {
        e.preventDefault();
        
        // Uncheck all attribute filters
        $('.attribute-filter').prop('checked', false);
        
        // Reset price filter if possible
        const $minPrice = $('input[name="min_price"]');
        const $maxPrice = $('input[name="max_price"]');
        if ($minPrice.length && $maxPrice.length) {
            $minPrice.val('');
            $maxPrice.val('');
        }
        
        // Reset WooCommerce price slider
        const $priceSlider = $('.price_slider');
        if ($priceSlider.length && $priceSlider.slider) {
            const min = $priceSlider.slider('option', 'min');
            const max = $priceSlider.slider('option', 'max');
            $priceSlider.slider('values', [min, max]);
            $('.price_slider_amount #min_price').val(min);
            $('.price_slider_amount #max_price').val(max);
        }
        
        // Reset sorting to default
        const $orderby = $('.woocommerce-ordering select');
        if ($orderby.length) {
            $orderby.val('menu_order');
        }
        
        // Reset page and reload
        state.currentPage = 1;
        loadProducts(false).catch(error => {
            console.error('Failed to clear filters:', error);
        });
    }

    /**
     * Debounced scroll handler for infinite scroll
     */
    let scrollTimeout = null;
    function handleScroll() {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        scrollTimeout = setTimeout(() => {
            checkInfiniteScroll();
        }, 150);
    }

    /**
     * Initialize all event listeners
     */
    function initEventListeners() {
        // Attribute filters - auto apply
        $(document).on('change', '.attribute-filter', handleAttributeChange);
        
        // Sorting - auto apply with prevent default
        $(document).on('change', '.woocommerce-ordering select', function(e) {
            e.preventDefault();
            handleSortingChange(e);
        });
        
        // Price slider button
        $(document).on('click', '.price_slider_amount .button', handlePriceFilter);
        
        // Clear filters
        $(document).on('click', '.clear-filters-btn', handleClearFilters);
        
        // Pagination clicks
        $(document).on('click', '#pagination-wrapper .page-numbers', handlePaginationClick);
        
        // Infinite scroll with debounce
        $(window).on('scroll', handleScroll);
        
        // Prevent default form submissions that might cause reload
        $(document).on('submit', '.woocommerce-ordering', function(e) {
            e.preventDefault();
            return false;
        });
    }

    /**
     * Get initial max pages from pagination
     */
    function getInitialMaxPages() {
        const $pagination = $('#pagination-wrapper .pagination');
        if ($pagination.length) {
            const $links = $pagination.find('.page-numbers');
            let maxPage = 1;
            
            $links.each(function() {
                const text = $(this).text();
                const num = parseInt(text);
                if (!isNaN(num) && text !== '...') {
                    if (num > maxPage) maxPage = num;
                }
            });
            
            state.maxPages = maxPage;
        }
        
        // Get current page from URL
        const urlParams = new URLSearchParams(window.location.search);
        const paged = urlParams.get('paged');
        if (paged && !isNaN(paged)) {
            state.currentPage = parseInt(paged);
        }
        
        // Update active pagination
        updateActivePagination();
    }

    /**
     * Check URL for filters on initial load
     */
    function initFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        let hasFilters = false;
        
        urlParams.forEach((value, key) => {
            if (key.startsWith('filter_') || key === 'min_price' || key === 'max_price') {
                hasFilters = true;
                
                if (key.startsWith('filter_')) {
                    const values = value.split(',');
                    values.forEach(val => {
                        $(`.attribute-filter[value="${val}"]`).prop('checked', true);
                    });
                }
            }
        });
        
        // If there are filters, load products via AJAX
        if (hasFilters && !state.initialLoad) {
            loadProducts(false).catch(error => {
                console.error('Failed to load initial products:', error);
            });
        } else {
            // Initialize infinite scroll
            if (state.maxPages > 1) {
                setTimeout(checkInfiniteScroll, 500);
            }
        }
        
        state.initialLoad = false;
        
        // Initialize product interactions for existing products
        initProductInteractions();
    }

    /**
     * Initialize the entire module
     */
    function init() {
        getInitialMaxPages();
        initEventListeners();
        initFromURL();
    }

    // Start when DOM ready
    $(document).ready(init);
    
})(jQuery);