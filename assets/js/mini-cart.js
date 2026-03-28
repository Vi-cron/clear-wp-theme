/**
 * Universal Add to Cart Script with Mini Cart
 * Version: 2.5 - Fixed all issues
 */

(function($) {
    'use strict';

    // ============================================
    // Конфигурация
    // ============================================
    const config = {
        ajaxUrl: (typeof wc_mini_cart_params !== 'undefined' && wc_mini_cart_params.ajax_url) 
            ? wc_mini_cart_params.ajax_url 
            : '/wp-admin/admin-ajax.php',
        nonce: (typeof wc_mini_cart_params !== 'undefined' && wc_mini_cart_params.nonce) 
            ? wc_mini_cart_params.nonce 
            : '',
        cartUrl: (typeof wc_mini_cart_params !== 'undefined' && wc_mini_cart_params.cart_url) 
            ? wc_mini_cart_params.cart_url 
            : '/cart/',
        checkoutUrl: (typeof wc_mini_cart_params !== 'undefined' && wc_mini_cart_params.checkout_url) 
            ? wc_mini_cart_params.checkout_url 
            : '/checkout/',
        miniCartRefreshDelay: 300,
        notificationDuration: 3000
    };

    // ============================================
    // Утилиты
    // ============================================
    const utils = {
        showNotification: function(message, type = 'success', duration = config.notificationDuration, showCartBtn = true) {
            const existingNotification = $('.wc-notification');
            if (existingNotification.length) {
                existingNotification.remove();
            }

            const cartBtnHtml = showCartBtn && type === 'success' ? 
                `<a href="${config.cartUrl}" class="wc-notification-cart-btn">Перейти в корзину →</a>` : '';

            const notification = $(`
                <div class="wc-notification wc-notification-${type}">
                    <div class="wc-notification-content">
                        <span class="wc-notification-icon">${type === 'success' ? '✓' : (type === 'error' ? '✗' : 'ℹ')}</span>
                        <span class="wc-notification-message">${message}</span>
                        <button class="wc-notification-close">&times;</button>
                    </div>
                    ${cartBtnHtml}
                </div>
            `);

            $('body').append(notification);
            
            setTimeout(() => notification.addClass('show'), 10);
            
            notification.find('.wc-notification-close').on('click', () => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            });
            
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
            
            return notification;
        },

        showError: function(message) {
            this.showNotification(message, 'error', 4000, false);
        },

        showSuccess: function(message) {
            this.showNotification(message, 'success', 3000, true);
        },

        formatPrice: function(price) {
            return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
        },

        getQuantityFromForm: function($form) {
            const $quantityInput = $form.find('.qty, input[name="quantity"]');
            if ($quantityInput.length) {
                let qty = parseInt($quantityInput.val());
                return isNaN(qty) || qty < 1 ? 1 : qty;
            }
            return 1;
        },

        getVariationAttributes: function($form) {
            const attributes = {};
            let isValid = true;
            
            $form.find('.variations select, .variations input[type="radio"]:checked, .variations input[type="hidden"]').each(function() {
                const $this = $(this);
                let name = $this.attr('name');
                let value = $this.val();
                
                if (name && name.startsWith('attribute_')) {
                    if (value && value !== '') {
                        attributes[name] = value;
                    } else {
                        const radioGroup = $form.find(`input[name="${name}"]:checked`);
                        if (radioGroup.length) {
                            attributes[name] = radioGroup.val();
                        } else if ($this.is('select') && (!$this.val() || $this.val() === '')) {
                            isValid = false;
                        }
                    }
                }
            });
            
            return { attributes, isValid };
        }
    };

    // ============================================
    // Класс Add to Cart
    // ============================================
    class AddToCart {
        constructor() {
            this.$buttons = $('.add-to-cart-btn');
            this.init();
        }

        init() {
            if (!this.$buttons.length) return;
            
            console.log('Found add to cart buttons:', this.$buttons.length);
            
            this.$buttons.each((index, btn) => {
                const $btn = $(btn);
                this.setupButton($btn);
            });
            
            // Слушаем обновление фрагментов корзины WooCommerce
            $(document.body).on('updated_cart_totals', () => {
                this.updateCartCount();
            });
        }

        setupButton($btn) {
            // Сохраняем оригинальный текст
            if (!$btn.data('original-text')) {
                $btn.data('original-text', $btn.html());
            }
            
            // Проверяем, есть ли форма вокруг кнопки
            let $form = $btn.closest('form.cart, form.variations_form');
            
            if ($form.length) {
                $btn.data('has-form', true);
                $btn.data('form', $form);
            } else {
                $btn.data('has-form', false);
            }
            
            // Обработчик клика
            $btn.off('click.add-to-cart').on('click.add-to-cart', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.addToCart($btn);
            });
        }

        addToCart($btn) {
            if ($btn.hasClass('loading') || $btn.hasClass('added')) return;
            
            const hasForm = $btn.data('has-form');
            
            // Анимация загрузки
            this.setLoading($btn, true);
            
            if (hasForm) {
                this.addFromForm($btn);
            } else {
                this.addFromButton($btn);
            }
        }

        addFromForm($btn) {
            const $form = $btn.data('form');
            
            if (!$form || !$form.length) {
                this.handleError($btn, 'Форма товара не найдена');
                return;
            }
            
            const productId = this.getProductIdFromForm($form);
            if (!productId) {
                this.handleError($btn, 'ID товара не найден');
                return;
            }
            
            const quantity = utils.getQuantityFromForm($form);
            const { attributes, isValid: attributesValid } = utils.getVariationAttributes($form);
            
            if (!attributesValid) {
                this.handleError($btn, 'Пожалуйста, выберите все характеристики товара');
                return;
            }
            
            const variationId = this.getVariationId($form);
            
            const postData = {
                product_id: productId,
                quantity: quantity,
                ...attributes
            };
            
            if (variationId && variationId !== '0') {
                postData.variation_id = variationId;
            }
            
            this.sendRequest(postData, $btn);
        }

        addFromButton($btn) {
            const productId = $btn.data('product-id');
            
            if (!productId) {
                this.handleError($btn, 'ID товара не указан');
                return;
            }
            
            this.sendRequest({
                product_id: parseInt(productId),
                quantity: 1
            }, $btn);
        }

        sendRequest(data, $btn) {
            const postData = {
                action: 'wc_theme_add_to_cart',
                product_id: data.product_id,
                quantity: data.quantity,
                nonce: config.nonce
            };
            
            if (data.variation_id && data.variation_id !== '0') {
                postData.variation_id = data.variation_id;
            }
            
            // Добавляем атрибуты вариаций
            for (const [key, value] of Object.entries(data)) {
                if (key.startsWith('attribute_')) {
                    postData[key] = value;
                }
            }
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.handleSuccess($btn, data.product_id, data.quantity);
                    } else {
                        let errorMsg = response.data?.message || response.message || 'Не удалось добавить товар в корзину';
                        this.handleError($btn, errorMsg);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', error);
                    this.handleError($btn, 'Ошибка при добавлении товара');
                }
            });
        }

        getProductIdFromForm($form) {
            let id = $form.find('input[name="add-to-cart"]').val();
            if (id) return parseInt(id);
            
            id = $form.data('product_id');
            if (id) return parseInt(id);
            
            id = $form.closest('[data-product-id]').data('product-id');
            if (id) return parseInt(id);
            
            return null;
        }

        getVariationId($form) {
            const $variationId = $form.find('input[name="variation_id"]');
            if ($variationId.length && $variationId.val()) {
                return $variationId.val();
            }
            return 0;
        }

        setLoading($btn, isLoading) {
            if (isLoading) {
                $btn.addClass('loading').prop('disabled', true);
                const originalHtml = $btn.data('original-text') || $btn.html();
                $btn.data('original-text', originalHtml);
                $btn.html('<span class="btn-text">Добавление...</span><span class="loading-spinner-btn"></span>');
            } else {
                $btn.removeClass('loading').prop('disabled', false);
                if (!$btn.hasClass('added')) {
                    const originalHtml = $btn.data('original-text') || '';
                    if (originalHtml) {
                        $btn.html(originalHtml);
                    }
                }
            }
        }

        showSuccessAnimation($btn) {
            $btn.addClass('added');
            $btn.html('<span class="btn-text">Добавлено!</span><span class="added-checkmark">✓</span>');
            
            setTimeout(() => {
                if ($btn.data('original-text')) {
                    $btn.html($btn.data('original-text'));
                }
                $btn.removeClass('added');
            }, 2000);
        }

        handleSuccess($btn, productId, quantity) {
            console.log('Product added successfully');
            
            // Обновляем счетчик корзины
            this.updateCartCount();
            
            // Обновляем фрагменты корзины
            $(document.body).trigger('wc_fragment_refresh');
            
            // Анимация успешного добавления
            this.showSuccessAnimation($btn);
            
            // Показываем уведомление
            utils.showSuccess('Товар успешно добавлен в корзину!');
            
            // Обновляем мини-корзину, если она открыта
            if (window.wcThemeMiniCart && window.wcThemeMiniCart.isOpen) {
                window.wcThemeMiniCart.refresh();
            }
        }

        handleError($btn, message) {
            console.error('Add to cart error:', message);
            utils.showError(message);
            this.setLoading($btn, false);
        }

        updateCartCount() {
            $(document.body).trigger('wc_fragment_refresh');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_cart_count',
                    nonce: config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success && response.data?.count !== undefined) {
                        $('.cart-count').text(response.data.count);
                    }
                }
            });
        }
    }

    // ============================================
    // Класс Mini Cart
    // ============================================
    class MiniCart {
        constructor() {
            this.$cartLink = $('.cart-link');
            this.isOpen = false;
            this.$miniCart = null;
            this.isLoading = false;
            this.hoverTimer = null;
            this.init();
        }

        init() {
            if (!this.$cartLink.length) return;
            
            this.$cartLink.addClass('has-mini-cart');
            
            // Определяем touch устройство
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            
            if (isTouchDevice) {
                // Для touch устройств - только по клику
                this.$cartLink.on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggle();
                });
            } else {
                // Для десктопа - ховер с задержкой
                this.$cartLink.on('mouseenter', () => {
                    clearTimeout(this.hoverTimer);
                    this.hoverTimer = setTimeout(() => this.open(), 200);
                });
                
                this.$cartLink.on('mouseleave', () => {
                    clearTimeout(this.hoverTimer);
                    this.hoverTimer = setTimeout(() => this.close(), 300);
                });
            }
            
            // Закрытие при клике вне
            $(document).on('click', (e) => {
                if (this.isOpen && !$(e.target).closest('.mini-cart, .cart-link').length) {
                    this.close();
                }
            });
            
            // Обновление при изменении корзины
            $(document.body).on('updated_cart_totals wc_fragment_refresh', () => {
                this.updateCartCount();
                if (this.isOpen) {
                    this.refresh();
                }
            });
            
            window.wcThemeMiniCart = this;
        }
        
        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
        
        open() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            
            if (!this.$miniCart) {
                this.createMiniCartBase();
                this.loadMiniCart();
            } else {
                this.$miniCart.addClass('show');
                this.isOpen = true;
                this.isLoading = false;
            }
        }
        
        createMiniCartBase() {
            this.$miniCart = $(`
                <div class="mini-cart loading">
                    <div class="mini-cart-header">
                        <span class="mini-cart-title">Корзина</span>
                        <span class="mini-cart-count">--</span>
                        <button class="mini-cart-close" title="Закрыть">×</button>
                    </div>
                    <div class="mini-cart-items">
                        <div class="mini-cart-loading">
                            <div class="loading-spinner"></div>
                            <span>Загрузка...</span>
                        </div>
                    </div>
                    <div class="mini-cart-footer">
                        <div class="mini-cart-total">
                            <span>Итого:</span>
                            <span class="mini-cart-total-price">--</span>
                        </div>
                        <div class="mini-cart-buttons">
                            <a href="${config.cartUrl}" class="mini-cart-btn mini-cart-btn-primary">Перейти в корзину</a>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(this.$miniCart);
            this.positionMiniCart();
            this.$miniCart.addClass('show');
            this.isOpen = true;
            
            // Кнопка закрытия
            this.$miniCart.find('.mini-cart-close').on('click', () => {
                this.close();
            });
        }

        loadMiniCart() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_mini_cart',
                    nonce: config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.renderMiniCart(response.data);
                    } else {
                        this.renderEmptyCart();
                    }
                },
                error: () => {
                    this.renderEmptyCart();
                },
                complete: () => {
                    this.isLoading = false;
                }
            });
        }

        renderMiniCart(data) {
            if (!this.$miniCart) return;
            
            const cartItems = data.items || [];
            const cartTotal = data.total_html || '0 ₽';
            const cartCount = data.count || 0;
            
            let itemsHtml = '';
            
            if (cartItems.length === 0) {
                itemsHtml = '<div class="mini-cart-empty">Корзина пуста</div>';
            } else {
                cartItems.forEach(item => {
                    itemsHtml += `
                        <div class="mini-cart-item" data-item-key="${item.key}">
                            <div class="mini-cart-item-image">
                                <a href="${item.url}">
                                    <img src="${item.image}" alt="${this.escapeHtml(item.title)}" loading="lazy">
                                </a>
                            </div>
                            <div class="mini-cart-item-info">
                                <a href="${item.url}" class="mini-cart-item-title">${this.escapeHtml(item.title)}</a>
                                <div class="mini-cart-item-price">
                                    ${item.quantity} × ${item.price_html}
                                </div>
                            </div>
                            <button class="mini-cart-item-remove" data-item-key="${item.key}" title="Удалить">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;
                });
            }
            
            this.$miniCart.removeClass('loading');
            this.$miniCart.find('.mini-cart-items').html(itemsHtml);
            this.$miniCart.find('.mini-cart-count').text(this.declension(cartCount, ['товар', 'товара', 'товаров']));
            this.$miniCart.find('.mini-cart-total-price').html(cartTotal);
            
            this.bindEvents();
        }

        renderEmptyCart() {
            if (!this.$miniCart) return;
            
            this.$miniCart.removeClass('loading');
            this.$miniCart.find('.mini-cart-items').html('<div class="mini-cart-empty">Корзина пуста</div>');
            this.$miniCart.find('.mini-cart-count').text('0 товаров');
            this.$miniCart.find('.mini-cart-total-price').html('0 ₽');
            
            this.bindEvents();
        }
        
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        positionMiniCart() {
            if (!this.$miniCart) return;
            
            const $cartLink = this.$cartLink;
            const offset = $cartLink.offset();
            const windowWidth = $(window).width();
            const miniCartWidth = 380;
            
            let right = windowWidth - (offset.left + $cartLink.outerWidth());
            right = Math.min(Math.max(right, 10), windowWidth - miniCartWidth - 10);
            
            this.$miniCart.css({
                top: offset.top + $cartLink.outerHeight() + 10,
                right: right,
                left: 'auto'
            });
        }

        bindEvents() {
            if (!this.$miniCart) return;
            
            // При наведении на мини-корзину - отменяем закрытие
            this.$miniCart.off('mouseenter').on('mouseenter', () => {
                clearTimeout(this.hoverTimer);
            });
            
            this.$miniCart.off('mouseleave').on('mouseleave', () => {
                clearTimeout(this.hoverTimer);
                this.hoverTimer = setTimeout(() => this.close(), 300);
            });
            
            // Удаление товара
            this.$miniCart.off('click', '.mini-cart-item-remove').on('click', '.mini-cart-item-remove', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(e.currentTarget);
                const itemKey = $btn.data('item-key');
                this.removeItem(itemKey, $btn);
            });
            
            // Очистка корзины
            this.$miniCart.off('click', '.mini-cart-clear').on('click', '.mini-cart-clear', (e) => {
                e.preventDefault();
                if (confirm('Очистить всю корзину?')) {
                    this.clearCart();
                }
            });
        }

        removeItem(itemKey, $btn) {
            $btn.addClass('loading');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_remove_cart_item',
                    item_key: itemKey,
                    nonce: config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.refresh();
                        utils.showNotification('Товар удален из корзины', 'info', 2000, false);
                        $(document.body).trigger('wc_fragment_refresh');
                    } else {
                        utils.showError(response.data?.message || 'Не удалось удалить товар');
                    }
                },
                complete: () => {
                    $btn.removeClass('loading');
                }
            });
        }

        clearCart() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_clear_cart',
                    nonce: config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.refresh();
                        utils.showNotification('Корзина очищена', 'info', 2000, false);
                        $(document.body).trigger('wc_fragment_refresh');
                    }
                }
            });
        }

        refresh() {
            if (this.isOpen) {
                this.showLoading();
                this.loadMiniCart();
            }
        }
        
        showLoading() {
            if (this.$miniCart && this.$miniCart.length) {
                this.$miniCart.addClass('loading');
                this.$miniCart.find('.mini-cart-items').html('<div class="mini-cart-loading"><div class="loading-spinner"></div><span>Загрузка...</span></div>');
            }
        }
        
        updateCartCount() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_cart_count',
                    nonce: config.nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success && response.data?.count !== undefined) {
                        $('.cart-count').text(response.data.count);
                    }
                }
            });
        }

        close() {
            clearTimeout(this.hoverTimer);
            if (this.$miniCart) {
                this.$miniCart.removeClass('show');
            }
            this.isOpen = false;
        }

        declension(n, titles) {
            return n + ' ' + titles[n % 10 === 1 && n % 100 !== 11 ? 0 : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 1 : 2];
        }
    }

    // ============================================
    // Инициализация
    // ============================================
    $(document).ready(() => {
        console.log('Document ready, initializing...');
        
        window.wcThemeAddToCart = new AddToCart();
        window.wcThemeMiniCart = new MiniCart();
        
        $(document).on('wc_theme_refresh_add_to_cart', () => {
            window.wcThemeAddToCart = new AddToCart();
        });
    });

})(jQuery);