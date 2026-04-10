/**
 * AJAX Catalog with filters
 * Version: 2.0 - Без конфликтов с mini-cart.js
 */

(function($) {
    'use strict';

    // Проверяем, не инициализирован ли уже модуль
    if (typeof window.wcAjaxModule !== 'undefined') {
        return;
    }

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

    const elements = {
        $grid: $('#products-grid'),
        $paginationWrapper: $('#pagination-wrapper'),
        $shopContent: $('#shop-content'),
        $resultCount: $('.woocommerce-result-count'),
		$clearFilters:$('.clear-filters-btn')
    };

    function getCurrentCategory() {
        const urlParams = new URLSearchParams(window.location.search);
        const catFromUrl = urlParams.get('product_cat');
        if (catFromUrl) {
            return catFromUrl;
        }
        
        if (window.location.pathname.includes('/product-category/')) {
            const parts = window.location.pathname.split('/');
            const categoryIndex = parts.indexOf('product-category');
            if (categoryIndex !== -1 && parts[categoryIndex + 1]) {
                return parts[categoryIndex + 1];
            }
        }
        
        return null;
    }

    function collectFilters() {
        const filters = {
            paged: state.currentPage
        };

        const currentCategory = getCurrentCategory();
        if (currentCategory && !window.location.pathname.includes('/shop/')) {
            filters.product_cat = currentCategory;
        }

        // Price filter
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

    function showLoading(append = false) {
        if (!append) {
            elements.$grid.addClass('loading');
            if (!$('#grid-loading-overlay').length) {
                elements.$grid.append('<div id="grid-loading-overlay" class="grid-loading-overlay"><div class="loading-spinner"></div></div>');
            } else {
                $('#grid-loading-overlay').show();
            }
        } else {
            if (!$('#load-more-indicator').length) {
                elements.$grid.after('<div id="load-more-indicator" class="load-more-indicator active"><div class="loading-spinner"></div></div>');
            } else {
                $('#load-more-indicator').addClass('active').html('<div class="loading-spinner"></div>');
            }
        }
    }

    function hideLoading(append = false) {
        if (!append) {
            elements.$grid.removeClass('loading');
            $('#grid-loading-overlay').remove();
        } else {
            $('#load-more-indicator').remove();
        }
    }

    function updateURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const filterKeys = ['min_price', 'max_price', 'orderby', 'paged', 'product_cat'];
        for (let key of urlParams.keys()) {
            if (key.startsWith('filter_') || filterKeys.includes(key)) {
                urlParams.delete(key);
            }
        }
        
        Object.keys(state.filters).forEach(key => {
            if (key !== 'paged' && state.filters[key] && state.filters[key] !== '') {
                if (Array.isArray(state.filters[key])) {
                    urlParams.set(key, state.filters[key].join(','));
                } else {
                    urlParams.set(key, state.filters[key]);
                }
            }
        });
        
        if (state.currentPage > 1) {
            urlParams.set('paged', state.currentPage);
        } else {
            urlParams.delete('paged');
        }
        
        const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        window.history.pushState({}, '', newUrl);
    }

    function updateActivePagination() {
        $('.pagination .page-numbers').removeClass('current');
        
        $('.pagination .page-numbers').each(function() {
            const pageText = $(this).text();
            const pageNum = parseInt(pageText);
            if (!isNaN(pageNum) && pageNum === state.currentPage) {
                $(this).addClass('current');
            }
        });
        
        if (state.currentPage >= state.maxPages) {
            $('.pagination .next').hide();
        } else {
            $('.pagination .next').show();
        }
        
        if (state.currentPage <= 1) {
            $('.pagination .prev').hide();
        } else {
            $('.pagination .prev').show();
        }
    }

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
            showLoading(append);
            
            $.ajax({
                url: wc_ajax_module.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_products_ajax',
                    filters: filters,
                    nonce: wc_ajax_module.nonce
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        const html = response.data.html;
                        state.maxPages = response.data.max_pages;
                        state.totalProducts = response.data.total_products || 0;
                        state.productsPerPage = response.data.products_per_page || 12;
                        
                        const start = ((state.currentPage - 1) * state.productsPerPage) + 1;
                        const end = Math.min(state.currentPage * state.productsPerPage, state.totalProducts);
                        
                        if (append) {
                            elements.$grid.append(html);
                        } else {
                            elements.$grid.html(html);
                            updatePagination(response.data.pagination);
                        }
                        
                        updateActivePagination();
                        updateURL();
                        
                        // Триггерим обновление для других скриптов (включая mini-cart)
                        
                        $(document).trigger('wc_ajax_products_loaded');
						
						//карта товара 
						$(document.body).trigger('wc_fragment_refresh');
						$(document).trigger('wc_theme_refresh_add_to_cart');
                        
                        resolve(response.data);
                    } else {
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
                    
                    if (state.currentPage < state.maxPages) {
                        setTimeout(checkInfiniteScroll, 100);
                    }
                }
            });
        });
    }

    function updatePagination(paginationHtml) {
        if (paginationHtml) {
            elements.$paginationWrapper.html(paginationHtml);
        } else {
            elements.$paginationWrapper.empty();
        }
    }
	
	function checkActiveFilters() {
    let hasActiveFilters = false;
    
    // 1. Проверяем атрибуты
    if ($('.attribute-filter:checked').length > 0) {
        hasActiveFilters = true;
    }
    
    // 2. Проверяем ценовой фильтр
    const $minPriceWc = $('#min_price');
    const $maxPriceWc = $('#max_price');
    
    if ($minPriceWc.length && $maxPriceWc.length) {
        const minPrice = $minPriceWc.val();
        const maxPrice = $maxPriceWc.val();
        
        // Получаем значения по умолчанию
        let defaultMin = 0;
        let defaultMax = 0;
        
        const $priceSlider = $('.price_slider');
        if ($priceSlider.length && $priceSlider.slider) {
            defaultMin = $priceSlider.slider('option', 'min');
            defaultMax = $priceSlider.slider('option', 'max');
            
            if (minPrice != defaultMin || maxPrice != defaultMax) {
                hasActiveFilters = true;
            }
        } else if (minPrice && maxPrice && minPrice !== '' && maxPrice !== '') {
            // Если нет слайдера, проверяем отличаются ли значения от начальных
            const initialMin = $minPriceWc.data('initial-min') || 0;
            const initialMax = $maxPriceWc.data('initial-max') || 1000;
            
            if (minPrice != initialMin || maxPrice != initialMax) {
                hasActiveFilters = true;
            }
        }
    }
    
    // 3. Проверяем сортировку
    const $orderby = $('.woocommerce-ordering select');
    if ($orderby.length && $orderby.val() !== 'menu_order') {
        hasActiveFilters = true;
    }
    
    // Показываем или скрываем кнопку
    if (hasActiveFilters) {
        elements.$clearFilters.removeClass('hide');
		} else {
			elements.$clearFilters.addClass('hide');
		}
		
	}

    function applyFilters() {
        if (state.filterTimeout) {
            clearTimeout(state.filterTimeout);
        }
        
        state.filterTimeout = setTimeout(() => {
            if (state.ajaxRunning) {
                state.filterTimeout = setTimeout(() => {
                    applyFilters();
                }, 100);
                return;
            }
            
            state.currentPage = 1;
            loadProducts(false).catch(error => {
                console.error('Failed to apply filters:', error);
            });
			checkActiveFilters();
        }, 500);
    }

    function loadNextPage() {
        if (state.isLoadingMore || state.currentPage >= state.maxPages || state.ajaxRunning) {
            return;
        }
        
        state.isLoadingMore = true;
        const nextPage = state.currentPage + 1;
        const originalPage = state.currentPage;
        state.currentPage = nextPage;
        
        loadProducts(true)
            .then(() => {
                state.isLoadingMore = false;
            })
            .catch((error) => {
                console.error('Failed to load next page:', error);
                state.isLoadingMore = false;
                state.currentPage = originalPage;
            });
    }

    function checkInfiniteScroll() {
        if (state.isLoadingMore || state.ajaxRunning || state.currentPage >= state.maxPages) {
            return;
        }
        
        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const documentHeight = $(document).height();
        const scrollPosition = scrollTop + windowHeight;
        const threshold = 300;
        
        if (scrollPosition >= documentHeight - threshold) {
            loadNextPage();
        }
    }

    function handlePaginationClick(e) {
        e.preventDefault();
        const $this = $(this);
        
        if ($this.hasClass('current') || state.ajaxRunning) return;
        
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
            
            $('html, body').animate({
                scrollTop: elements.$shopContent.offset().top - 100
            }, 400);
        }
    }

    function handleAttributeChange() {
        applyFilters();
    }

    function handleSortingChange(e) {
        e.preventDefault();
        applyFilters();
    }

    function handlePriceFilter(e) {
        e.preventDefault();
        applyFilters();
    }

    function handleClearFilters(e) {
        e.preventDefault();
        
        $('.attribute-filter').prop('checked', false);
        
        const $minPriceWc = $('.price_slider_amount #min_price');
        const $maxPriceWc = $('.price_slider_amount #max_price');
        if ($minPriceWc.length && $maxPriceWc.length) {
            const $priceSlider = $('.price_slider');
            if ($priceSlider.length && $priceSlider.slider) {
                const min = $priceSlider.slider('option', 'min');
                const max = $priceSlider.slider('option', 'max');
                $priceSlider.slider('values', [min, max]);
                $minPriceWc.val(min);
                $maxPriceWc.val(max);
            }
        }
        
        const $orderby = $('.woocommerce-ordering select');
        if ($orderby.length) {
            $orderby.val('menu_order');
        }
        
        state.currentPage = 1;
		elements.$clearFilters.addClass('hide');
		
        loadProducts(false).catch(error => {
            console.error('Failed to clear filters:', error);
        });
		
    }

    let scrollTimeout = null;
    function handleScroll() {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        scrollTimeout = setTimeout(() => {
            checkInfiniteScroll();
        }, 150);
    }

    function initEventListeners() {
        $(document).on('change', '.attribute-filter', handleAttributeChange);
        $(document).on('change', '.woocommerce-ordering select', handleSortingChange);
        $(document).on('click', '.price_slider_amount .button', handlePriceFilter);
        $(document).on('click', '.clear-filters-btn', handleClearFilters);
        $(document).on('click', '#pagination-wrapper .page-numbers', handlePaginationClick);
        $(window).on('scroll', handleScroll);
        
        $(document).on('submit', '.woocommerce-ordering', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Кнопка "Применить фильтры"
        $(document).on('click', '.apply-filters-btn', function(e) {
            e.preventDefault();
            applyFilters();
        });
    }

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
        
        const urlParams = new URLSearchParams(window.location.search);
        const paged = urlParams.get('paged');
        if (paged && !isNaN(paged)) {
            state.currentPage = parseInt(paged);
        }
        
        updateActivePagination();
    }

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
        
        if (hasFilters && !state.initialLoad) {
            loadProducts(false).catch(error => {
                console.error('Failed to load initial products:', error);
            });
        } else if (state.maxPages > 1) {
            setTimeout(checkInfiniteScroll, 500);
        }
        
        state.initialLoad = false;
    }

    function init() {
        if (!elements.$grid.length) return;
        
        getInitialMaxPages();
        initEventListeners();
        initFromURL();
        
        // Маркируем модуль как инициализированный
        window.wcAjaxModule = true;
    }

    $(document).ready(init);
    
})(jQuery);