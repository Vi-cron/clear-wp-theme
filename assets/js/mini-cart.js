/**
 * Universal Add to Cart Script with Mini Cart
 * Version: 2.0
 * Dependencies: jQuery, WooCommerce AJAX
 */

(function($) {
    'use strict';

    // ============================================
    // Конфигурация
    // ============================================
    const config = {
        ajaxUrl: wc_ajax?.ajax_url || (window.wc_add_to_cart_params?.ajax_url || '/wp-admin/admin-ajax.php'),
        nonce: wc_ajax?.nonce || window.wc_add_to_cart_params?.wc_ajax_nonce || '',
        addToCartNonce: wc_add_to_cart_params?.wc_ajax_nonce || '',
        cartUrl: wc_add_to_cart_params?.cart_url || '/cart/',
        checkoutUrl: wc_add_to_cart_params?.checkout_url || '/checkout/',
        miniCartRefreshDelay: 300,
        notificationDuration: 3000,
        loadingClass: 'loading',
        addedClass: 'added',
        disabledClass: 'disabled',
        cartCountSelector: '.cart-count',
        cartLinkSelector: '.cart-link',
        wishlistCountSelector: '.wishlist-count'
    };

    // ============================================
    // Утилиты
    // ============================================
    const utils = {
        showNotification: function(message, type = 'success', duration = config.notificationDuration) {
            const existingNotification = $('.wc-notification');
            if (existingNotification.length) {
                existingNotification.remove();
            }

            const notification = $(`
                <div class="wc-notification wc-notification-${type}">
                    <div class="wc-notification-content">
                        <span class="wc-notification-icon">${type === 'success' ? '✓' : '⚠'}</span>
                        <span class="wc-notification-message">${message}</span>
                    </div>
                    ${type === 'success' ? '<a href="' + config.cartUrl + '" class="wc-notification-cart-btn">Перейти в корзину →</a>' : ''}
                </div>
            `);

            $('body').append(notification);
            
            setTimeout(() => notification.addClass('show'), 10);
            
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, duration);
        },

        showError: function(message) {
            this.showNotification(message, 'error', 4000);
        },

        formatPrice: function(price) {
            return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        getQuantityFromForm: function($form) {
            const $quantityInput = $form.find('.qty, input[name="quantity"]');
            if ($quantityInput.length) {
                return parseInt($quantityInput.val()) || 1;
            }
            return 1;
        },

        getVariationIdFromForm: function($form) {
            const $variationId = $form.find('input[name="variation_id"]');
            if ($variationId.length && $variationId.val()) {
                return parseInt($variationId.val());
            }
            return 0;
        },

        getAttributesFromForm: function($form) {
            const attributes = {};
            $form.find('input[name^="attribute_"], select[name^="attribute_"]').each(function() {
                const $this = $(this);
                let value = $this.val();
                if ($this.is('input[type="radio"]') && !$this.is(':checked')) return;
                if ($this.is('input[type="checkbox"]') && !$this.is(':checked')) return;
                if (value) {
                    attributes[$this.attr('name')] = value;
                }
            });
            return attributes;
        }
    };

    // ============================================
    // Класс Mini Cart
    // ============================================
    class MiniCart {
        constructor() {
            this.$cartLink = $(config.cartLinkSelector);
            this.isOpen = false;
            this.isLoading = false;
            this.$miniCart = null;
            this.closeTimeout = null;
            
            this.init();
        }

        init() {
            if (!this.$cartLink.length) return;
            
            this.$cartLink.addClass('has-mini-cart');
            
            // Десктоп: ховер
            if (window.innerWidth > 992) {
                this.$cartLink.on('mouseenter', () => this.open());
                this.$cartLink.on('mouseleave', (e) => {
                    if (!$(e.relatedTarget).closest('.mini-cart').length) {
                        this.closeWithDelay();
                    }
                });
            } 
            // Мобильные/планшеты: клик
            else {
                this.$cartLink.on('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.isOpen ? this.close() : this.open();
                });
                
                $(document).on('click', (e) => {
                    if (this.isOpen && !$(e.target).closest('.mini-cart, .cart-link').length) {
                        this.close();
                    }
                });
            }
            
            // Предзагрузка корзины при первом открытии
            this.$cartLink.one('mouseenter click', () => {
                if (!this.isOpen) this.loadMiniCart();
            });
        }

        loadMiniCart() {
            if (this.isLoading) return;
            this.isLoading = true;
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_mini_cart',
                    nonce: config.nonce
                },
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
            if (this.$miniCart) this.$miniCart.remove();
            
            const cartItems = data.items || [];
            const cartTotal = data.total || 0;
            const cartCount = data.count || 0;
            
            let itemsHtml = '';
            
            cartItems.forEach(item => {
                itemsHtml += `
                    <div class="mini-cart-item" data-item-key="${item.key}">
                        <div class="mini-cart-item-image">
                            <a href="${item.url}">
                                <img src="${item.image}" alt="${item.title}" loading="lazy">
                            </a>
                        </div>
                        <div class="mini-cart-item-info">
                            <a href="${item.url}" class="mini-cart-item-title">${item.title}</a>
                            <div class="mini-cart-item-price">
                                ${item.quantity} × ${utils.formatPrice(item.price)}
                            </div>
                        </div>
                        <button class="mini-cart-item-remove" data-item-key="${item.key}" title="Удалить">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                `;
            });
            
            this.$miniCart = $(`
                <div class="mini-cart">
                    <div class="mini-cart-header">
                        <span class="mini-cart-title">Корзина</span>
                        <span class="mini-cart-count">${cartCount} ${this.declension(cartCount, ['товар', 'товара', 'товаров'])}</span>
                        <button class="mini-cart-clear" ${cartCount === 0 ? 'disabled' : ''}>Очистить</button>
                    </div>
                    <div class="mini-cart-items">
                        ${itemsHtml || '<div class="mini-cart-empty">Корзина пуста</div>'}
                    </div>
                    <div class="mini-cart-footer">
                        <div class="mini-cart-total">
                            <span>Итого:</span>
                            <span class="mini-cart-total-price">${utils.formatPrice(cartTotal)}</span>
                        </div>
                        <div class="mini-cart-buttons">
                            <a href="${config.cartUrl}" class="mini-cart-btn mini-cart-btn-primary">В корзину</a>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(this.$miniCart);
            this.positionMiniCart();
            this.bindMiniCartEvents();
        }

        renderEmptyCart() {
            if (this.$miniCart) this.$miniCart.remove();
            
            this.$miniCart = $(`
                <div class="mini-cart">
                    <div class="mini-cart-header">
                        <span class="mini-cart-title">Корзина</span>
                        <span class="mini-cart-count">0 товаров</span>
                    </div>
                    <div class="mini-cart-items">
                        <div class="mini-cart-empty">Корзина пуста</div>
                    </div>
                    <div class="mini-cart-footer">
                        <div class="mini-cart-total">
                            <span>Итого:</span>
                            <span class="mini-cart-total-price">0 ₽</span>
                        </div>
                        <div class="mini-cart-buttons">
                            <a href="${config.cartUrl}" class="mini-cart-btn mini-cart-btn-primary">В корзину</a>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(this.$miniCart);
            this.positionMiniCart();
            this.bindMiniCartEvents();
        }

        positionMiniCart() {
            if (!this.$miniCart) return;
            
            const $cartLink = this.$cartLink;
            const offset = $cartLink.offset();
            
            this.$miniCart.css({
                top: offset.top + $cartLink.outerHeight() + 10,
                right: $(window).width() - (offset.left + $cartLink.outerWidth())
            });
        }

        bindMiniCartEvents() {
            // Удаление товара
            this.$miniCart.on('click', '.mini-cart-item-remove', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const itemKey = $btn.data('item-key');
                this.removeItem(itemKey, $btn);
            });
            
            // Очистка корзины
            this.$miniCart.on('click', '.mini-cart-clear', (e) => {
                e.preventDefault();
                if (confirm('Очистить всю корзину?')) {
                    this.clearCart();
                }
            });
            
            // Закрытие при клике вне (для мобильных)
            $(document).on('click.miniCart', (e) => {
                if (this.isOpen && !$(e.target).closest('.mini-cart, .cart-link').length) {
                    this.close();
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
                success: (response) => {
                    if (response.success) {
                        this.refresh();
                        utils.showNotification('Товар удален из корзины', 'info', 2000);
                    } else {
                        utils.showError('Не удалось удалить товар');
                    }
                },
                error: () => {
                    utils.showError('Ошибка при удалении товара');
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
                success: (response) => {
                    if (response.success) {
                        this.refresh();
                        utils.showNotification('Корзина очищена', 'info', 2000);
                    }
                },
                error: () => {
                    utils.showError('Ошибка при очистке корзины');
                }
            });
        }

        refresh() {
            this.loadMiniCart();
            this.updateCartCount();
        }

        updateCartCount() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_cart_count',
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $(config.cartCountSelector).text(response.data.count);
                    }
                }
            });
        }

        open() {
            if (this.closeTimeout) clearTimeout(this.closeTimeout);
            if (!this.$miniCart) {
                this.loadMiniCart();
                return;
            }
            this.$miniCart.addClass('show');
            this.isOpen = true;
        }

        close() {
            if (this.$miniCart) {
                this.$miniCart.removeClass('show');
            }
            this.isOpen = false;
        }

        closeWithDelay() {
            this.closeTimeout = setTimeout(() => this.close(), 300);
        }

        declension(n, titles) {
            return titles[n % 10 === 1 && n % 100 !== 11 ? 0 : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 1 : 2];
        }
    }

    // ============================================
    // Класс Add to Cart
    // ============================================
    class AddToCart {
        constructor() {
            this.$buttons = $('.add-to-cart-btn');
            this.miniCart = null;
            this.init();
        }

        init() {
            if (!this.$buttons.length) return;
            
            this.$buttons.each((index, btn) => {
                const $btn = $(btn);
                
                // Находим форму, в которой находится кнопка
                let $form = $btn.closest('form.cart, form.variations_form');
                
                // Если кнопка не в форме, ищем форму рядом
                if (!$form.length) {
                    $form = $btn.closest('.product-cart-form').find('form.cart, form.variations_form');
                }
                
                if (!$form.length) {
                    $form = $('<form>').addClass('cart');
                    $btn.wrap($form);
                    $form = $btn.parent();
                }
                
                // Сохраняем ссылку на форму
                $btn.data('form', $form);
                
                // Обработчик клика
                $btn.off('click.add-to-cart').on('click.add-to-cart', (e) => {
                    e.preventDefault();
                    this.addToCart($btn);
                });
            });
            
            // Инициализируем мини-корзину после загрузки
            setTimeout(() => {
                this.miniCart = new MiniCart();
            }, 100);
        }

        addToCart($btn) {
            if ($btn.hasClass(config.loadingClass) || $btn.hasClass(config.disabledClass)) return;
            
            const $form = $btn.data('form');
            if (!$form.length) {
                utils.showError('Ошибка: форма не найдена');
                return;
            }
            
            // Получаем данные товара
            const productId = parseInt($btn.data('product-id') || $form.find('input[name="add-to-cart"]').val());
            const quantity = utils.getQuantityFromForm($form);
            const variationId = utils.getVariationIdFromForm($form);
            const attributes = utils.getAttributesFromForm($form);
            
            if (!productId || isNaN(productId)) {
                utils.showError('Ошибка: ID товара не найден');
                return;
            }
            
            // Проверяем вариации
            if ($form.hasClass('variations_form')) {
                const $variationIdInput = $form.find('input[name="variation_id"]');
                if (!$variationIdInput.val() || $variationIdInput.val() === '0') {
                    utils.showError('Пожалуйста, выберите все характеристики товара');
                    return;
                }
            }
            
            // Анимация загрузки
            this.setLoading($btn, true);
            
            // Формируем данные для AJAX запроса
            const data = {
                action: 'wc_theme_add_to_cart',
                product_id: productId,
                quantity: quantity,
                nonce: config.addToCartNonce || config.nonce
            };
            
            if (variationId) data.variation_id = variationId;
            if (Object.keys(attributes).length) data.attributes = attributes;
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.handleSuccess($btn, response.data);
                    } else {
                        this.handleError($btn, response.data?.message || 'Ошибка при добавлении товара');
                    }
                },
                error: (xhr) => {
                    let errorMsg = 'Ошибка при добавлении товара';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.data?.message || errorMsg;
                    } catch(e) {}
                    this.handleError($btn, errorMsg);
                },
                complete: () => {
                    this.setLoading($btn, false);
                }
            });
        }

        setLoading($btn, isLoading) {
            if (isLoading) {
                $btn.addClass(config.loadingClass).prop('disabled', true);
                const originalText = $btn.html();
                $btn.data('original-text', originalText);
                $btn.html('<span class="loading-spinner-btn"></span>Добавляю...');
            } else {
                $btn.removeClass(config.loadingClass).prop('disabled', false);
                if ($btn.data('original-text') && !$btn.hasClass(config.addedClass)) {
                    $btn.html($btn.data('original-text'));
                }
            }
        }

        showSuccessAnimation($btn) {
            $btn.addClass(config.addedClass);
            const originalText = $btn.html();
            $btn.html('<span class="added-checkmark">✓</span>Добавлено!');
            
            setTimeout(() => {
                if ($btn.data('original-text')) {
                    $btn.html($btn.data('original-text'));
                } else {
                    $btn.html(originalText);
                }
                $btn.removeClass(config.addedClass);
            }, 2000);
        }

        handleSuccess($btn, data) {
            // Обновляем счетчик корзины
            if (data.cart_count !== undefined) {
                $(config.cartCountSelector).text(data.cart_count);
            } else {
                this.updateCartCount();
            }
            
            // Анимация успешного добавления
            this.showSuccessAnimation($btn);
            
            // Показываем уведомление
            utils.showNotification('Товар успешно добавлен в корзину!', 'success');
            
            // Обновляем мини-корзину
            if (this.miniCart) {
                setTimeout(() => this.miniCart.loadMiniCart(), 100);
            }
            
            // Событие для других плагинов
            $(document).trigger('wc_theme_added_to_cart', [data]);
        }

        handleError($btn, message) {
            utils.showError(message);
            $btn.removeClass(config.loadingClass).prop('disabled', false);
        }

        updateCartCount() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_theme_get_cart_count',
                    nonce: config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $(config.cartCountSelector).text(response.data.count);
                    }
                }
            });
        }
    }

    // ============================================
    // Инициализация
    // ============================================
    $(document).ready(() => {
        // Ждем загрузки WooCommerce
        if (typeof wc_add_to_cart_params !== 'undefined') {
            config.addToCartNonce = wc_add_to_cart_params.wc_ajax_nonce;
            config.cartUrl = wc_add_to_cart_params.cart_url;
            config.checkoutUrl = wc_add_to_cart_params.checkout_url;
        }
        
        window.wcThemeAddToCart = new AddToCart();
        
        // Инициализация для динамически добавленных кнопок
        $(document).on('wc_theme_refresh_add_to_cart', () => {
            window.wcThemeAddToCart = new AddToCart();
        });
    });

})(jQuery);