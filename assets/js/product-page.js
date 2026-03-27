/**
 * Product Page Scripts
 * Исправленная версия с проверками
 */

(function($) {
    'use strict';
    
    // Ждем полной загрузки DOM
    $(document).ready(function() {
        initProductPage();
    });
    
    function initProductPage() {
        // Проверяем, что мы на странице товара
        if (!document.body.classList.contains('single-product')) {
            return;
        }
        
        // Инициализируем компоненты только если они существуют
        initProductGallery();
        initProductTabs();
        initQuantityButtons();
        initVariations();
        initRelatedProductsSlider();
        initWishlistButton();
    }
    
    /**
     * Инициализация галереи товара
     */
    function initProductGallery() {
        const mainSwiperEl = document.querySelector('.product-gallery-main-swiper');
        const thumbsSwiperEl = document.querySelector('.product-gallery-thumbs-swiper');
        
        // Если Swiper не загружен, выходим
        if (typeof Swiper === 'undefined') {
            console.warn('Swiper не загружен');
            return;
        }
        
        // Инициализируем основной слайдер только если он существует
        let mainSwiper = null;
        if (mainSwiperEl) {
            mainSwiper = new Swiper(mainSwiperEl, {
                loop: true,
                spaceBetween: 10,
                navigation: {
                    nextEl: '.product-gallery-main-swiper .swiper-button-next',
                    prevEl: '.product-gallery-main-swiper .swiper-button-prev',
                },
                thumbs: {
                    swiper: null
                }
            });
        }
        
        // Инициализируем слайдер миниатюр
        let thumbsSwiper = null;
        if (thumbsSwiperEl) {
            thumbsSwiper = new Swiper(thumbsSwiperEl, {
                loop: false,
                spaceBetween: 10,
                slidesPerView: 4,
                freeMode: true,
                watchSlidesProgress: true,
                breakpoints: {
                    0: { slidesPerView: 3 },
                    768: { slidesPerView: 4 },
                    992: { slidesPerView: 5 }
                }
            });
        }
        
        // Связываем слайдеры
        if (mainSwiper && thumbsSwiper) {
            mainSwiper.params.thumbs.swiper = thumbsSwiper;
            mainSwiper.thumbs.init();
        }
        
        // Lightbox для изображений (если Fancybox загружен)
        //initLightbox();
    }
    
    /**
     * Инициализация Lightbox
     */
    function initLightbox() {
        const lightboxLinks = document.querySelectorAll('.product-gallery-lightbox');
        
        // Проверяем, что Fancybox доступен
        if (lightboxLinks.length && typeof window.Fancybox !== 'undefined') {
            lightboxLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Собираем все изображения в галерее
                    const allImages = Array.from(lightboxLinks).map(img => ({
                        src: img.href,
                        type: 'image'
                    }));
                    
                    const currentIndex = Array.from(lightboxLinks).indexOf(this);
                    
                    window.Fancybox.show(allImages, {
                        startIndex: currentIndex,
                        infinite: true,
                        Thumbs: { autoStart: true }
                    });
                });
            });
        }
    }
    
    /**
     * Инициализация табов
     */
    function initProductTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        
        if (!tabBtns.length) return;
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const tabId = this.dataset.tab;
                
                if (!tabId) return;
                
                // Убираем активные классы
                tabBtns.forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('active');
                });
                
                // Добавляем активные классы
                this.classList.add('active');
                const activePane = document.getElementById('tab-' + tabId);
                if (activePane) {
                    activePane.classList.add('active');
                }
            });
        });
    }
    
    /**
     * Инициализация кнопок количества
     */
    function initQuantityButtons() {
        const quantityWrappers = document.querySelectorAll('.quantity');
        
        if (!quantityWrappers.length) return;
        
        quantityWrappers.forEach(wrapper => {
            const minusBtn = wrapper.querySelector('.quantity-minus');
            const plusBtn = wrapper.querySelector('.quantity-plus');
            const input = wrapper.querySelector('.qty');
            
            if (!minusBtn || !plusBtn || !input) return;
            
            minusBtn.addEventListener('click', function(e) {
                e.preventDefault();
                let value = parseInt(input.value);
                const min = parseInt(input.getAttribute('min')) || 1;
                if (value > min) {
                    input.value = value - 1;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            plusBtn.addEventListener('click', function(e) {
                e.preventDefault();
                let value = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                if (!max || value < max) {
                    input.value = value + 1;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            input.addEventListener('change', function() {
                let value = parseInt(this.value);
                const min = parseInt(this.getAttribute('min')) || 1;
                const max = parseInt(this.getAttribute('max'));
                
                if (isNaN(value) || value < min) {
                    this.value = min;
                }
                if (max && value > max) {
                    this.value = max;
                }
            });
        });
    }
    
    /**
     * Инициализация вариаций товара
     */
    function initVariations() {
        const variationForms = document.querySelectorAll('.variations_form');
        
        if (!variationForms.length) return;
        
        variationForms.forEach(form => {
            const variationOptions = form.querySelectorAll('.variation-option input');
            const variationIdField = form.querySelector('.variation_id');
            const singleVariation = form.querySelector('.single_variation');
            const addToCartBtn = form.querySelector('.variation-add-to-cart');
            const quantityInput = form.querySelector('.qty');
            
            if (!variationOptions.length) return;
            
            // Получаем данные вариаций
            let variationsData = [];
            try {
                if (form.dataset.product_variations) {
                    variationsData = JSON.parse(form.dataset.product_variations);
                }
            } catch(e) {
                console.error('Ошибка парсинга вариаций:', e);
            }
            
            function updateVariation() {
                // Собираем выбранные атрибуты
                const selectedAttributes = {};
                variationOptions.forEach(option => {
                    if (option.checked) {
                        const attrName = option.dataset.attributeName;
                        if (attrName) {
                            selectedAttributes[attrName] = option.value;
                        }
                    }
                });
                
                // Ищем подходящую вариацию
                let foundVariation = null;
                for (let variation of variationsData) {
                    let match = true;
                    for (let [attrName, attrValue] of Object.entries(selectedAttributes)) {
                        if (variation.attributes && variation.attributes[attrName] !== attrValue) {
                            match = false;
                            break;
                        }
                    }
                    if (match) {
                        foundVariation = variation;
                        break;
                    }
                }
                
                if (foundVariation && foundVariation.is_in_stock) {
                    if (variationIdField) variationIdField.value = foundVariation.variation_id;
                    
                    // Обновляем отображение цены
                    if (singleVariation && foundVariation.price_html) {
                        singleVariation.innerHTML = `
                            <div class="woocommerce-variation-price">${foundVariation.price_html}</div>
                            <div class="woocommerce-variation-availability">
                                <p class="stock in-stock">В наличии</p>
                            </div>
                        `;
                    }
                    
                    if (addToCartBtn) {
                        addToCartBtn.disabled = false;
                        addToCartBtn.style.opacity = '1';
                    }
                    
                    if (quantityInput && foundVariation.max_qty) {
                        quantityInput.max = foundVariation.max_qty;
                    }
                } else {
                    if (variationIdField) variationIdField.value = '';
                    if (singleVariation) {
                        singleVariation.innerHTML = '<div class="woocommerce-variation-unavailable">Эта комбинация недоступна</div>';
                    }
                    if (addToCartBtn) {
                        addToCartBtn.disabled = true;
                        addToCartBtn.style.opacity = '0.5';
                    }
                }
            }
            
            // Обработчики для опций вариаций
            variationOptions.forEach(option => {
                option.addEventListener('change', function() {
                    const parentGroup = this.closest('.variation-item');
                    if (parentGroup) {
                        parentGroup.querySelectorAll('.variation-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        this.closest('.variation-option').classList.add('selected');
                    }
                    updateVariation();
                });
            });
            
            // Инициализация выбранных опций
            updateVariation();
        });
    }
    
    /**
     * Инициализация слайдера похожих товаров
     */
    function initRelatedProductsSlider() {
        const relatedSlider = document.querySelector('.related-products-swiper');
        
        if (!relatedSlider) return;
        if (typeof Swiper === 'undefined') return;
        
        // Проверяем, есть ли слайды
        const slides = relatedSlider.querySelectorAll('.swiper-slide');
        if (slides.length <= 1) return;
        
        new Swiper('.related-products-swiper', {
            slidesPerView: 1,
            spaceBetween: 10,
            navigation: {
                nextEl: '.related-products-swiper .swiper-button-next',
                prevEl: '.related-products-swiper .swiper-button-prev',
            },
            pagination: {
                el: '.related-products-swiper .swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                480: { slidesPerView: 2, spaceBetween: 10 },
                768: { slidesPerView: 3, spaceBetween: 15 },
                992: { slidesPerView: 4, spaceBetween: 20 }
            },
            loop: slides.length > 4,
            autoplay: false
        });
    }
    
    /**
     * Инициализация кнопки избранного
     */
    function initWishlistButton() {
        const wishlistBtn = document.querySelector('.single-wishlist-btn');
        
        if (!wishlistBtn) return;
        
        wishlistBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            
            if (productId) {
                // Здесь можно добавить AJAX запрос для избранного
                showNotification('Добавлено в избранное', 'success');
                this.classList.add('active');
            }
        });
    }
    
    /**
     * Показать уведомление
     */
    function showNotification(message, type = 'info') {
        // Удаляем старые уведомления
        const oldNotification = document.querySelector('.notification');
        if (oldNotification) {
            oldNotification.remove();
        }
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = message;
        document.body.appendChild(notification);
        
        // Анимация появления
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Автоматическое скрытие
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 3000);
    }
    
    // Добавляем стили для уведомлений, если их нет
    if (!document.querySelector('#notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 24px;
                background: #4caf50;
                color: #fff;
                border-radius: 5px;
                z-index: 9999;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                font-size: 14px;
            }
            .notification-error {
                background: #f44336;
            }
            .notification-info {
                background: #2196f3;
            }
            .notification-warning {
                background: #ff9800;
            }
        `;
        document.head.appendChild(style);
    }
    
})(jQuery);