document.addEventListener('DOMContentLoaded', () => {
    // ====================== Header Section Animations ======================
    // Swiper Carousel Initialization
    const swiper = new Swiper('.swiper-container', {
        effect: 'coverflow',
        grabCursor: true,
        centeredSlides: true,
        slidesPerView: 'auto',
        coverflowEffect: {
            rotate: () => Math.random() * 360,
            stretch: () => Math.random() * 80 - 40,
            depth: () => Math.random() * 500 + 150,
            modifier: () => Math.random() * 2.5,
            slideShadows: true,
        },
        loop: true,
        autoplay: {
            delay: Math.random() * 2000 + 500,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });

    // GSAP Animations for Swiper Slides
    gsap.to('.swiper-slide', {
        duration: () => Math.random() * 4 + 2,
        x: () => Math.random() * 300 - 150,
        y: () => Math.random() * 200 - 100,
        rotation: () => Math.random() * 360 - 180,
        scale: () => Math.random() * 0.6 + 0.8,
        opacity: () => Math.random() * 0.4 + 0.6,
        zIndex: () => Math.floor(Math.random() * 100 + 1),
        repeat: -1,
        yoyo: true,
        ease: 'power3.inOut',
        stagger: {
            amount: 2,
            from: 'random',
        },
    });

    // ====================== Sticky Offer Animations ======================
    const OFFER_ANIMATION_DELAY = 4000;
    const OFFER_GROUPS = document.querySelectorAll(".sticky-offer-group");

    OFFER_GROUPS.forEach((group) => {
        const offers = Array.from(group.querySelectorAll(".sticky-offer"));
        let activeIndex = 0;

        const animateOffers = () => {
            offers.forEach((offer) => {
                offer.style.opacity = "0";
                offer.style.transform = "scale(0.95) rotate(0deg)";
            });

            const activeOffer = offers[activeIndex];
            activeOffer.style.opacity = "1";
            activeOffer.style.transform = `scale(1.05) ${getSlideDirection(activeIndex)}`;
            activeOffer.style.transition = `opacity 1000ms ease-in-out, transform 1000ms ease-in-out`;

            activeIndex = (activeIndex + 1) % offers.length;
            setTimeout(animateOffers, OFFER_ANIMATION_DELAY);
        };

        const getSlideDirection = (index) => {
            const directions = ["translateX(0px)", "translateX(0px)", 
                              "translateY(0px)", "translateY(0px)"];
            return directions[index % directions.length];
        };

        animateOffers();
    });

    // ====================== Search System ======================
    const searchInput = document.getElementById('search-input');
    const suggestionsContainer = document.getElementById('suggestions');
    const proContainer = document.querySelector('.pro-container');
    const paginationSection = document.getElementById('pagination');
    let initialProducts = [];
    let initialPaginationHTML = paginationSection.innerHTML;
    let currentPage = 1;
    const itemsPerPage = 9;

    // Store initial products
    document.querySelectorAll('.pro').forEach(pro => {
        initialProducts.push(pro.outerHTML);
    });

    // Fetch data from server
    async function fetchData(query, page = 1) {
        try {
            const response = await fetch(`search.php?query=${encodeURIComponent(query)}&page=${page}&limit=${itemsPerPage}`);
            return await response.json();
        } catch (error) {
            console.error('Error:', error);
            return { results: [], suggestions: [] };
        }
    }

    // Highlight matching text
    function highlightText(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Autocomplete Suggestions
    searchInput.addEventListener('input', async () => {
        const query = searchInput.value.trim();
        suggestionsContainer.innerHTML = '';

        if (query.length > 1) {
            const data = await fetchData(query);
            showSuggestions(data.suggestions, query);
        } else {
            suggestionsContainer.style.display = 'none';
        }
    });

    function showSuggestions(suggestions, query) {
        suggestionsContainer.innerHTML = '';
        if (suggestions?.length > 0) {
            suggestions.forEach(suggestion => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.innerHTML = `
                    <div class="suggestion-icon-container">
                        <i class="fa-solid fa-magnifying-glass suggestion-icon"></i>
                    </div>
                    <div class="suggestion-content">
                        <div class="suggestion-main">${highlightText(suggestion.name, query)}</div>
                    </div>
                `;

                div.addEventListener('click', () => {
                    searchInput.value = suggestion.category ? suggestion.category : suggestion.name;
                    performSearch();
                    suggestionsContainer.style.display = 'none';
                });

                suggestionsContainer.appendChild(div);
            });
            suggestionsContainer.style.display = 'block';
        } else {
            suggestionsContainer.style.display = 'none';
        }
    }

    // Search Form Handler
    document.getElementById('search-form').addEventListener('submit', (e) => {
        e.preventDefault();
        suggestionsContainer.style.display = 'none';
        performSearch();
    });

    // Main Search Function
    async function performSearch(page = 1) {
        const query = searchInput.value.trim();
        suggestionsContainer.style.display = 'none';
        proContainer.innerHTML = '<div class="loading">Loading products...</div>';

        if (query.length > 0) {
            const data = await fetchData(query, page);
            displayResults(data.results);
            updatePagination(data.totalPages);
        } else {
            restoreInitialProducts();
        }
        addShowAllButton();
    }

    function displayResults(products) {
        proContainer.innerHTML = '';
        
        if (products?.length > 0) {
            products.forEach(product => {
                const proDiv = document.createElement('div');
                proDiv.className = 'pro';
                proDiv.innerHTML = `
                    <div class="image-container">
                        <img src="${product.image_url}" alt="${product.name}">
                    </div>
                    <div class="des">
                        <span>${product.name}</span>
                        <h5>${product.description}</h5>
                        <div class="star">
                            ${generateStarRating(product.ratings)}
                            <h9>(${parseFloat(product.ratings).toFixed(1)})</h9>
                        </div>
                        <h4>Ksh ${parseFloat(product.price).toLocaleString()}</h4>
                    </div>
                    <a href="#"><i class="fa fa-shopping-cart cart"></i></a>
                `;
                proDiv.onclick = () => window.location.href = `sproduct.php?id=${product.product_id}`;
                proContainer.appendChild(proDiv);
            });
        } else {
            proContainer.innerHTML = '<p class="no-results">No products found matching your search.</p>';
        }
    }

    function generateStarRating(rating) {
        const fullStars = Math.floor(rating);
        const halfStar = (rating - fullStars) >= 0.5 ? 1 : 0;
        const emptyStars = 5 - fullStars - halfStar;

        return `
            ${'<i class="fas fa-star"></i>'.repeat(fullStars)}
            ${halfStar ? '<i class="fas fa-star-half-alt"></i>' : ''}
            ${'<i class="far fa-star"></i>'.repeat(emptyStars)}
        `;
    }

    function updatePagination(totalPages) {
        paginationSection.innerHTML = '';
        
        for (let i = 1; i <= totalPages; i++) {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = i;
            if (i === currentPage) {
                link.style.background = '#7f00ff';
            }
            link.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage = i;
                performSearch(currentPage);
            });
            paginationSection.appendChild(link);
        }

        if (currentPage < totalPages) {
            const nextLink = document.createElement('a');
            nextLink.innerHTML = '<i class="fa fa-long-arrow-alt-right"></i>';
            nextLink.addEventListener('click', (e) => {
                e.preventDefault();
                currentPage++;
                performSearch(currentPage);
            });
            paginationSection.appendChild(nextLink);
        }
    }

    function restoreInitialProducts() {
        proContainer.innerHTML = initialProducts.join('');
        paginationSection.innerHTML = initialPaginationHTML;
    }

    function addShowAllButton() {
        if (!document.getElementById('show-all')) {
            const showAllBtn = document.createElement('button');
            showAllBtn.id = 'show-all';
            showAllBtn.className = 'show-all-btn';
            showAllBtn.textContent = 'Back Homepage';
            
            showAllBtn.addEventListener('click', () => {
                restoreInitialProducts();
                searchInput.value = '';
                showAllBtn.remove();
            });

            proContainer.parentNode.insertBefore(showAllBtn, proContainer.nextSibling);
        }
    }
});
//SPRODUCT PAGE//
 const Mainimg = document.getElementById("Mainimg");
        const smallimgs = document.getElementsByClassName("small-img");
        for (let img of smallimgs) {
            img.onclick = () => Mainimg.src = img.src;
        }

        // Function to handle variation button clicks
        function selectVariation(type, value) {
            // Open the modal
            openVariationModal();
            
            // Generate a safe ID by replacing spaces with hyphens
            const safeValue = value.replace(/\s+/g, '-');
            const inputId = `quantity-${type}-${safeValue}`;
            const input = document.getElementById(inputId);
            
            if (input) {
                // Set quantity to 1 if it was 0
                if (parseInt(input.value) === 0) {
                    input.value = 1;
                    // Update sticky price
                    updateStickyPrice();
                }
            }
        }

        // Unified function to adjust quantity
        function adjustQuantity(type, value, change, maxStock) {
            const safeValue = value.replace(/\s+/g, '-');
            const inputId = `quantity-${type}-${safeValue}`;
            const input = document.getElementById(inputId);
            
            if (!input) return;
            
            let newValue = parseInt(input.value) + change;
            
            // Enforce min and max values
            if (newValue < 0) newValue = 0;
            if (maxStock !== undefined && newValue > maxStock) newValue = maxStock;
            
            input.value = newValue;
            updateStickyPrice();
        }

        // Update sticky price in the modal and button
        function updateStickyPrice() {
            let total = 0;
            const quantityInputs = document.querySelectorAll('#variation-modal input[type="number"]');
            
            quantityInputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                const price = parseFloat(input.dataset.price) || 0;
                total += qty * price;
            });
            
            // Update the sticky price display
            document.getElementById('sticky-price').textContent = total.toFixed(2);
        }

        // Open variation modal
        function openVariationModal() {
            document.getElementById('variation-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            // Update prices when modal opens
            updateStickyPrice();
        }

        // Close variation modal
        function closeVariationModal() {
            document.getElementById('variation-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Size Guide Modal control
        function openSizeGuideModal() {
            document.getElementById('size-guide-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeSizeGuideModal() {
            document.getElementById('size-guide-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        
        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    if (modal.id === 'variation-modal') {
                        closeVariationModal();
                    } else if (modal.id === 'size-guide-modal') {
                        closeSizeGuideModal();
                    }
                }
            });
        });
        
        