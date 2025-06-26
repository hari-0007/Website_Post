document.addEventListener('DOMContentLoaded', () => {
    /**
     * ------------------------------------------------------------------------
     *  STATE & CONFIG
     * ------------------------------------------------------------------------
     */
    const state = {
        feedbackRecaptchaId: null,
        reportRecaptchaId: null,
        currentJobIdForReport: null,
    };

    const config = {
        cookieConsentName: serverData.cookieConsentName || 'cookie_consent_status',
        viewedJobsCookieName: serverData.viewedJobsCookieName || 'user_viewed_job_ids',
        joinPopupCookieName: serverData.joinPopupCookieName || 'join_channels_popup_shown',
        recaptchaSiteKey: serverData.recaptchaSiteKey,
    };

    const allJobs = serverData.jobs || [];

    /**
     * ------------------------------------------------------------------------
     *  DOM ELEMENTS
     * ------------------------------------------------------------------------
     */
    const elements = {
        // Main containers
        jobListingsContainer: document.getElementById('job-listings-container'),
        mainContainer: document.querySelector('.container'),
        // Modals
        authModal: document.getElementById('authModal'),
        shareModal: document.getElementById('jobShareModal'),
        cautionModal: document.getElementById('jobCautionModal'),
        reportFormContainer: document.getElementById('reportJobFormContainer'),
        reportedJobInfo: document.getElementById('reportedJobInfo'),
        reportIssueBtn: document.getElementById('reportIssueBtn'),
        cautionUnderstoodBtn: document.getElementById('cautionUnderstoodBtn'),
        reportStatusMessage: document.getElementById('reportStatusMessage'),
        joinChannelsPopup: document.getElementById('joinChannelsPopup'),
        // Forms
        searchForm: document.querySelector('.search-bar form'),
        loginForm: document.getElementById('loginForm'),
        registerForm: document.getElementById('registerForm'),
        feedbackForm: document.getElementById('feedbackForm'),
        reportForm: document.getElementById('reportForm'),
        // Banners
        cookieBanner: document.getElementById('cookieConsentBanner'),
        // Interactive areas
        menuToggle: document.getElementById('menuToggle'),
        mobileNav: document.getElementById('mobileNav'),
        mobileNavOverlay: document.getElementById('mobileNavOverlay'),
        closeMenuBtn: document.querySelector('.close-menu-btn'),
        sidebar: document.querySelector('.sidebar'),
        mobileFilters: document.querySelector('.mobile-filters'),
    };

    /**
     * ------------------------------------------------------------------------
     *  HELPER FUNCTIONS
     * ------------------------------------------------------------------------
     */
    const setCookie = (name, value, days) => {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = `${name}=${value || ""}${expires}; path=/; SameSite=Lax`;
    };

    const getCookie = (name) => {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let c of ca) {
            c = c.trim();
            if (c.startsWith(nameEQ)) return c.substring(nameEQ.length);
        }
        return null;
    };

    const lockBackground = () => {
        if (elements.mainContainer) elements.mainContainer.classList.add('page-content-blur');
        document.body.classList.add('modal-open');
    };

    const unlockBackground = () => {
        if (elements.mainContainer) elements.mainContainer.classList.remove('page-content-blur');
        document.body.classList.remove('modal-open');
    };

    /**
     * ------------------------------------------------------------------------
     *  CORE LOGIC
     * ------------------------------------------------------------------------
     */

    // --- AJAX Navigation ---
    const handleAjaxNavigation = (url) => {
        const ajaxUrl = new URL(url, window.location.origin);
        ajaxUrl.searchParams.set('ajax', '1');

        if (!elements.jobListingsContainer) return;

        elements.jobListingsContainer.classList.add('loading-content');
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        elements.jobListingsContainer.appendChild(spinner);

        fetch(ajaxUrl.toString())
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok.');
                return response.text();
            })
            .then(html => {
                history.pushState({}, '', url);
                elements.jobListingsContainer.innerHTML = html;
                updateActiveFilterLinks(url);
                elements.jobListingsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(error => {
                console.error('Error fetching jobs:', error);
                elements.jobListingsContainer.innerHTML = '<p class="no-jobs-message">Error loading jobs. Please try again.</p>';
            })
            .finally(() => {
                elements.jobListingsContainer.classList.remove('loading-content');
            });
    };

    // --- Job Card Interaction ---
    const toggleJobDetails = (jobCard) => {
        const details = jobCard.querySelector('.job-details');
        const summary = jobCard.querySelector('.job-summary');
        if (!details || !summary) return;

        const isCurrentlyCollapsed = details.style.display === 'none';

        // Collapse all other cards
        document.querySelectorAll('.job-card.job-card-active').forEach(activeCard => {
            if (activeCard !== jobCard) {
                activeCard.querySelector('.job-details').style.display = 'none';
                activeCard.querySelector('.job-summary').style.display = '';
                activeCard.classList.remove('job-card-active');
            }
        });

        // Toggle the clicked card
        if (isCurrentlyCollapsed) {
            details.style.display = 'block';
            summary.style.display = 'none';
            jobCard.classList.add('job-card-active');
            jobCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            logJobView(jobCard.dataset.jobId);
        } else {
            details.style.display = 'none';
            summary.style.display = '';
            jobCard.classList.remove('job-card-active');
        }
    };

    const logJobView = (jobId) => {
        if (!jobId) return;

        // Increment view count via API
        fetch('increment_job_view.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: jobId })
        }).catch(console.error);

        // Store in cookie for recommendations
        if (getCookie(config.cookieConsentName) === 'accepted') {
            let viewedIds = [];
            try {
                viewedIds = JSON.parse(getCookie(config.viewedJobsCookieName) || '[]');
                if (!Array.isArray(viewedIds)) viewedIds = [];
            } catch (e) { viewedIds = []; }

            viewedIds = [jobId, ...viewedIds.filter(id => id !== jobId)].slice(0, 10);
            setCookie(config.viewedJobsCookieName, JSON.stringify(viewedIds), 30);
        }
    };

    const expandJobFromUrl = () => {
        const jobId = new URLSearchParams(window.location.search).get('job_id');
        if (jobId) {
            const jobCard = document.querySelector(`.job-card[data-job-id="${jobId}"]`);
            if (jobCard) {
                toggleJobDetails(jobCard);
            }
        }
    };

    // --- Sidebar Counts ---
    const updateSidebarCounts = () => {
        const counts = {
            All: allJobs.length,
            Remote: allJobs.filter(j => j.type === 'remote').length,
            Onsite: allJobs.filter(j => j.type === 'onsite').length,
            Hybrid: allJobs.filter(j => j.type === 'hybrid').length,
            FullTime: allJobs.filter(j => j.type === 'full time').length,
            PartTime: allJobs.filter(j => j.type === 'part time').length,
            Internship: allJobs.filter(j => j.type === 'internship').length,
            Developer: allJobs.filter(j => j.type === 'developer').length,
        };

        const now = Date.now();
        const day = 24 * 60 * 60 * 1000;
        const validDateJobs = allJobs.filter(j => j.posted_on_unix_ts > 0);
        counts['1'] = validDateJobs.filter(j => now - (j.posted_on_unix_ts * 1000) <= day).length;
        counts['7'] = validDateJobs.filter(j => now - (j.posted_on_unix_ts * 1000) <= 7 * day).length;
        counts['30'] = validDateJobs.filter(j => now - (j.posted_on_unix_ts * 1000) <= 30 * day).length;

        for (const key in counts) {
            document.querySelectorAll(`[data-count-id="count${key}"]`).forEach(el => el.innerText = counts[key]);
        }
    };

    const updateActiveFilterLinks = (urlString) => {
        const url = new URL(urlString, window.location.origin);
        const params = url.searchParams;
        const currentType = params.get('type') || '';
        const currentFilter = params.get('filter') || 'all';
        const isRecommendations = params.has('recommendations');

        document.querySelectorAll('.sidebar a, .mobile-filters a').forEach(link => {
            const linkUrl = new URL(link.href);
            const linkParams = linkUrl.searchParams;
            let isActive = false;

            if (linkParams.has('recommendations')) {
                isActive = isRecommendations;
            } else if (!isRecommendations) {
                const linkType = linkParams.get('type') || '';
                const linkFilter = linkParams.get('filter') || 'all';
                // A link is active if its primary filter matches the URL's, and other filters are compatible
                if (linkType === currentType && linkFilter === currentFilter) {
                    isActive = true;
                }
            }
            link.classList.toggle('active-filter', isActive);
        });
    };

    /**
     * ------------------------------------------------------------------------
     *  MODAL & POPUP LOGIC
     * ------------------------------------------------------------------------
     */

    // --- Auth Modal ---
    const openAuthModal = (view = 'login') => {
        if (!elements.authModal) return;
        elements.loginForm.reset();
        elements.registerForm.reset();
        document.querySelectorAll('.feedback-message').forEach(el => el.style.display = 'none');

        lockBackground();
        elements.authModal.querySelector('#authLoginView').style.display = (view === 'login') ? 'block' : 'none';
        elements.authModal.querySelector('#authRegisterView').style.display = (view === 'register') ? 'block' : 'none';
        elements.authModal.style.display = 'flex';
    };
    window.openAuthModal = openAuthModal; // Make it globally accessible for inline onclick

    const closeAuthModal = () => {
        if (elements.authModal) elements.authModal.style.display = 'none';
        unlockBackground();
    };
    window.closeAuthModal = closeAuthModal;

    const showLoginView = (e) => { e.preventDefault(); openAuthModal('login'); };
    window.showLoginView = showLoginView;

    const showRegisterView = (e) => { e.preventDefault(); openAuthModal('register'); };
    window.showRegisterView = showRegisterView;

    // --- Mobile Menu ---
    const openMobileMenu = () => {
        document.body.classList.add('mobile-nav-open');
        lockBackground(); // Re-use existing function to prevent scrolling
    };

    const closeMobileMenu = () => {
        document.body.classList.remove('mobile-nav-open');
        unlockBackground(); // Re-use existing function
    };
    window.closeMobileMenu = closeMobileMenu; // Make global for inline onclick in PHP


    // --- Share Modal ---
    const openShareModal = (jobId, title, company) => {
        if (!elements.shareModal) return;
        const jobTitleEl = elements.shareModal.querySelector('.share-modal-job-title');
        jobTitleEl.textContent = `${title} at ${company}`;

        const baseUrl = `${window.location.origin}${window.location.pathname}`;
        const jobUrl = `${baseUrl}?job_id=${encodeURIComponent(jobId)}`;
        const shareText = `Check out this job: ${title} at ${company}`;
        const encodedUrl = encodeURIComponent(jobUrl);
        const encodedText = encodeURIComponent(shareText);

        elements.shareModal.querySelector('#copyJobLinkButton').dataset.url = jobUrl;
        elements.shareModal.querySelector('#shareViaWhatsApp').href = `https://wa.me/?text=${encodedText}%20${encodedUrl}`;
        elements.shareModal.querySelector('#shareViaLinkedIn').href = `https://www.linkedin.com/shareArticle?mini=true&url=${encodedUrl}&title=${encodedText}`;
        elements.shareModal.querySelector('#shareViaEmail').href = `mailto:?subject=${encodedText}&body=${encodedText}%0A%0A${jobUrl}`;

        lockBackground();
        elements.shareModal.style.display = 'flex';
    };

    // --- Caution & Report Modal ---
    const resetReportForm = () => {
        if (elements.reportFormContainer) elements.reportFormContainer.style.display = 'none';
        if (elements.reportIssueBtn) {
            elements.reportIssueBtn.textContent = 'Report Issue';
            elements.reportIssueBtn.style.display = 'inline-block';
        }
        if (elements.cautionUnderstoodBtn) {
            elements.cautionUnderstoodBtn.textContent = 'Understood';
        }
        if (elements.reportForm) elements.reportForm.reset();

        if (elements.reportStatusMessage) {
            elements.reportStatusMessage.textContent = '';
            elements.reportStatusMessage.style.display = 'none';
        }
        if (elements.reportedJobInfo) {
            elements.reportedJobInfo.style.display = 'none';
        }
        if (state.reportRecaptchaId !== null) {
            grecaptcha.reset(state.reportRecaptchaId);
        }
    };

    const showCautionAlert = (jobCard) => {
        if (!elements.cautionModal) return;
        state.currentJobIdForReport = jobCard.dataset.jobId;
        resetReportForm();
        lockBackground();
        elements.cautionModal.style.display = 'block';
        elements.cautionModal.classList.add('is-opening');
        elements.cautionModal.addEventListener('animationend', () => {
            elements.cautionModal.classList.remove('is-opening');
        }, { once: true });
    };

    const closeCautionAlert = () => {
        if (!elements.cautionModal) return;
        elements.cautionModal.classList.add('is-closing');
        elements.cautionModal.addEventListener('animationend', () => {
            elements.cautionModal.style.display = 'none';
            elements.cautionModal.classList.remove('is-closing');
            unlockBackground();
            resetReportForm();
        }, { once: true });
    };

    const toggleReportForm = () => {
        if (!elements.reportFormContainer) return;
        const isHidden = elements.reportFormContainer.style.display === 'none';
        if (isHidden) {
            elements.reportFormContainer.style.display = 'block';
            const job = allJobs.find(j => j.id === state.currentJobIdForReport);
            if (job && elements.reportedJobInfo) {
                elements.reportedJobInfo.textContent = `Reporting job: "${job.title || 'N/A'}" at "${job.company || 'N/A'}"`;
                elements.reportedJobInfo.style.display = 'block';
            }
            elements.reportIssueBtn.textContent = 'Submit Report';
            elements.cautionUnderstoodBtn.textContent = 'Cancel';
        } else {
            resetReportForm();
        }
    };

    const submitReport = () => {
        const reason = document.getElementById('reportReason')?.value.trim();
        if (!reason) {
            elements.reportStatusMessage.textContent = 'Please provide a reason for the report.';
            elements.reportStatusMessage.style.color = 'red';
            elements.reportStatusMessage.style.display = 'block';
            return;
        }

        const reportData = {
            job_id: state.currentJobIdForReport,
            reporter_name: document.getElementById('reportName')?.value.trim(),
            reporter_email: document.getElementById('reportEmail')?.value.trim(),
            reason: reason,
            'g-recaptcha-response': grecaptcha.getResponse(state.reportRecaptchaId)
        };

        elements.reportStatusMessage.textContent = 'Submitting...';
        elements.reportStatusMessage.style.color = 'blue';
        elements.reportStatusMessage.style.display = 'block';

        fetch('report_job.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(reportData)
        })
        .then(res => res.json())
        .then(data => {
            elements.reportStatusMessage.textContent = data.message;
            elements.reportStatusMessage.style.color = data.success ? 'green' : 'red';
            if (data.success) {
                elements.reportIssueBtn.style.display = 'none';
                elements.cautionUnderstoodBtn.textContent = 'Close';
            }
            grecaptcha.reset(state.reportRecaptchaId);
        }).catch(err => {
            console.error('Report submission error:', err);
            elements.reportStatusMessage.textContent = 'An error occurred.';
            elements.reportStatusMessage.style.color = 'red';
        });
    };

    // --- Join Channels Popup ---
    const initJoinChannelsPopup = () => {
        if (elements.joinChannelsPopup && !getCookie(config.joinPopupCookieName)) {
            setTimeout(() => {
                lockBackground();
                elements.joinChannelsPopup.style.display = 'flex';
            }, 1500);
        }
    };
    const closeJoinChannelsPopup = () => {
        if (elements.joinChannelsPopup) {
            elements.joinChannelsPopup.style.display = 'none';
            unlockBackground();
        }
        setCookie(config.joinPopupCookieName, 'true', 1);
    };
    window.closeJoinChannelsPopup = closeJoinChannelsPopup;
    window.handleJoinChannelsClick = closeJoinChannelsPopup;

    // --- Generic Modals ---
    window.openWModal = () => {
        document.getElementById('modal').style.display = 'flex';
        lockBackground();
    };
    window.closeWModal = () => {
        document.getElementById('modal').style.display = 'none';
        unlockBackground();
    };

    /**
     * ------------------------------------------------------------------------
     *  ADVERTISEMENT LOGIC
     * ------------------------------------------------------------------------
     */
    const initAdRefresher = () => {
        // IMPORTANT: Refreshing ads too frequently (e.g., faster than every 30-60 seconds)
        // can violate Google AdSense policies and is strongly discouraged.
        // The 5-second interval used here is for demonstration based on the request
        // and should be increased to a safe value (e.g., 30000 ms) for production.
        const refreshInterval = 5000; // 5 seconds. CHANGE TO 30000+ FOR PRODUCTION.

        setInterval(() => {
            const adContainer = document.getElementById('in-feed-ad-container');
            // Only refresh if the ad container is on the page
            if (adContainer) {
                // To refresh, we clear the container and re-insert a new <ins> tag.
                // This signals to AdSense that there's a new slot to fill.
                adContainer.innerHTML = ''; // Clear previous ad content

                const newAdSlot = document.createElement('ins');
                newAdSlot.className = 'adsbygoogle';
                newAdSlot.style.display = 'block';
                newAdSlot.dataset.adFormat = 'fluid';
                newAdSlot.dataset.adLayoutKey = '-fb+5w+4e-db+86';
                newAdSlot.dataset.adClient = 'ca-pub-5503439974043365';
                newAdSlot.dataset.adSlot = '8172806315';

                adContainer.appendChild(newAdSlot);

                // Request a new ad for the newly created slot.
                try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { console.error("AdSense push error:", e); }
            }
        }, refreshInterval);
    };

    /**
     * ------------------------------------------------------------------------
     *  EVENT LISTENERS & BINDINGS
     * ------------------------------------------------------------------------
     */

    // --- Mobile Menu Listeners ---
    if (elements.menuToggle) {
        elements.menuToggle.addEventListener('click', openMobileMenu);
    }
    if (elements.closeMenuBtn) {
        elements.closeMenuBtn.addEventListener('click', closeMobileMenu);
    }
    if (elements.mobileNavOverlay) {
        elements.mobileNavOverlay.addEventListener('click', closeMobileMenu);
    }


    // --- Feedback Form Interactivity ---
    const feedbackTextarea = elements.feedbackForm?.querySelector('textarea[name="message"]');
    if (feedbackTextarea) {
        feedbackTextarea.addEventListener('input', () => {
            const starsContainer = document.getElementById('feedbackStarsContainer');
            const recaptchaContainer = document.getElementById('feedbackRecaptchaContainer');
            if (starsContainer) starsContainer.style.display = 'block';
            if (recaptchaContainer) recaptchaContainer.style.display = 'block';
        }, { once: true }); // Use { once: true } so the listener removes itself after the first input
    }

    // Star Rating Logic
    const stars = document.querySelectorAll('#feedbackStarRating span');
    const ratingInput = document.getElementById('feedbackRatingInput');
    if (stars.length > 0 && ratingInput) {
        let currentRating = 0;
        stars.forEach((star, index) => {
            star.addEventListener('mouseover', () => {
                stars.forEach((s, i) => s.classList.toggle('hover', i <= index));
            });
            star.addEventListener('mouseout', () => {
                stars.forEach(s => s.classList.remove('hover'));
                // Re-apply selected class based on currentRating
                stars.forEach((s, i) => s.classList.toggle('selected', i < currentRating));
            });
            star.addEventListener('click', () => {
                currentRating = index + 1;
                ratingInput.value = currentRating;
                stars.forEach((s, i) => s.classList.toggle('selected', i < currentRating));
            });
        });
        document.getElementById('feedbackStarRating')?.addEventListener('mouseleave', () => {
            stars.forEach(s => s.classList.remove('hover'));
        });
    }

    // --- Main Navigation & Interaction (Event Delegation) ---
    document.body.addEventListener('click', (e) => {
        // --- Job Card Interactions ---
        const jobCard = e.target.closest('.job-card');
        if (jobCard) {
            // If the card is an ad, do not trigger job card interactions
            if (jobCard.classList.contains('ad-card')) {
                return;
            }

            const shareBtn = e.target.closest('.share-button');
            const applyBtn = e.target.closest('.apply-button');
            const cautionBtn = e.target.closest('.job-caution-alert');

            if (shareBtn) {
                e.stopPropagation();
                const title = jobCard.querySelector('h3').firstChild.textContent.trim();
                const company = jobCard.querySelector('.job-card-company-location strong').textContent.trim();
                openShareModal(jobCard.dataset.jobId, title, company);
                return;
            }

            if (applyBtn) {
                e.stopPropagation();
                if (applyBtn.dataset.authAction) {
                    openAuthModal(applyBtn.dataset.authAction);
                } else {
                    // Handle direct application logic
                }
                return;
            }

            if (cautionBtn) {
                e.stopPropagation();
                showCautionAlert(jobCard);
                return;
            }

            // If no button was clicked, toggle details
            toggleJobDetails(jobCard);
        }

        // --- Filter & Pagination Links ---
        const navLink = e.target.closest('.sidebar a, .mobile-filters a, .pagination-container a:not(.disabled)');
        if (navLink) {
            e.preventDefault();
            handleAjaxNavigation(navLink.href);
        }

        // --- Modal Close Buttons ---
        // The close button for caution modal is removed from HTML, so no need to handle its click here.

        // --- Close Modal on Overlay Click ---
        if (e.target.matches('.modal, .share-modal-overlay')) {
            if (e.target.id !== 'jobCautionModal') { // Caution modal has its own close logic
                e.target.style.display = 'none';
                unlockBackground();
            }
        }

        // --- Copy Link Button ---
        if (e.target.matches('#copyJobLinkButton')) {
            const urlToCopy = e.target.dataset.url;
            const feedbackEl = elements.shareModal.querySelector('#copyLinkFeedback');
            navigator.clipboard.writeText(urlToCopy).then(() => {
                feedbackEl.textContent = 'Link copied!';
                feedbackEl.style.color = 'green';
                setTimeout(() => { // Also unlock background when share modal auto-closes
                    if (elements.shareModal) elements.shareModal.style.display = 'none';
                    feedbackEl.textContent = '';
                }, 1500);
            }).catch(err => {
                feedbackEl.textContent = 'Failed to copy.';
                feedbackEl.style.color = 'red';
                console.error('Failed to copy link:', err);
            });
        }

        // --- Caution Modal Buttons ---
        if (e.target.matches('#cautionUnderstoodBtn')) {
            if (e.target.textContent === 'Cancel') {
                resetReportForm();
            } else {
                closeCautionAlert();
            }
        }
        if (e.target.matches('#reportIssueBtn')) {
            e.target.textContent === 'Submit Report' ? submitReport() : toggleReportForm();
        }
    });

    // --- Form Submissions ---
    if (elements.searchForm) {
        elements.searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const searchTerm = new FormData(elements.searchForm).get('search');
            const params = new URLSearchParams({
                search: searchTerm,
                filter: 'all',
                type: '',
                page: '1'
            });
            handleAjaxNavigation(`${window.location.pathname}?${params.toString()}`);
        });
    }

    const handleAuthFormSubmit = (form, errorMessageEl) => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');
            errorMessageEl.style.display = 'none';

            fetch('auth.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        errorMessageEl.textContent = data.message || 'An unknown error occurred.';
                        errorMessageEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    errorMessageEl.textContent = 'A network error occurred.';
                    errorMessageEl.style.display = 'block';
                });
        });
    };

    if (elements.loginForm) {
        handleAuthFormSubmit(elements.loginForm, document.getElementById('loginErrorMessage'));
    }
    if (elements.registerForm) {
        handleAuthFormSubmit(elements.registerForm, document.getElementById('registerErrorMessage'));
    }

    if (elements.feedbackForm) {
        elements.feedbackForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(elements.feedbackForm);
            const recaptchaResponse = grecaptcha.getResponse(state.feedbackRecaptchaId);
            if (!recaptchaResponse) {
                alert('Please complete the reCAPTCHA.');
                return;
            }
            formData.append('g-recaptcha-response', recaptchaResponse);

            const responseBox = document.getElementById('responseMsg');
            fetch('feedback.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    responseBox.textContent = data.message;
                    responseBox.className = `feedback-message ${data.success ? 'success' : 'error'}`;
                    responseBox.style.display = 'block';
                    if (data.success) {
                        elements.feedbackForm.reset();
                    }
                    grecaptcha.reset(state.feedbackRecaptchaId);
                    setTimeout(() => responseBox.style.display = 'none', 5000);
                })
                .catch(err => {
                    console.error("Feedback form error:", err);
                    responseBox.textContent = 'An error occurred. Please try again.';
                    responseBox.className = 'feedback-message error';
                    responseBox.style.display = 'block';
                });
        });
    }

    // --- Cookie Consent ---
    if (elements.cookieBanner && !getCookie(config.cookieConsentName)) {
        elements.cookieBanner.style.display = 'block';
    }
    document.getElementById('acceptCookieConsent')?.addEventListener('click', () => {
        setCookie(config.cookieConsentName, 'accepted', 365);
        if (elements.cookieBanner) elements.cookieBanner.style.display = 'none';
    });

    /**
     * ------------------------------------------------------------------------
     *  INITIALIZATION
     * ------------------------------------------------------------------------
     */
    const init = () => {
        updateSidebarCounts();
        updateActiveFilterLinks(window.location.href);
        expandJobFromUrl();
        initJoinChannelsPopup();
        initAdRefresher();
    };

    init();

    /**
     * ------------------------------------------------------------------------
     *  GLOBAL FUNCTIONS (for external scripts like reCAPTCHA)
     * ------------------------------------------------------------------------
     */
    window.onloadRecaptchaCallback = () => {
        const feedbackContainer = document.getElementById('feedbackRecaptchaContainer');
        if (feedbackContainer && config.recaptchaSiteKey) {
            state.feedbackRecaptchaId = grecaptcha.render(feedbackContainer, {
                'sitekey': config.recaptchaSiteKey
            });
        }
        const reportContainer = document.getElementById('reportRecaptchaContainer');
        if (reportContainer && config.recaptchaSiteKey) {
            state.reportRecaptchaId = grecaptcha.render(reportContainer, {
                'sitekey': config.recaptchaSiteKey
            });
        }
    };
});

/*
 * The following Three.js animation code has been removed as per the request.
 * If you wish to re-enable it, uncomment this block and the corresponding
 * HTML div and script import in index.php, and the call in main.js's init function.
 */
/*

    const initThreeJS = () => {
        const container = document.getElementById('title-card-3d-background');
        if (!container || !window.THREE) return;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
        camera.position.z = 30;

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setSize(container.offsetWidth, container.offsetHeight);
        renderer.setPixelRatio(window.devicePixelRatio);
        container.appendChild(renderer.domElement);

        const geometry = new THREE.IcosahedronGeometry(10, 0);
        const material = new THREE.MeshBasicMaterial({
            color: 0x60a5fa,
            wireframe: true,
            transparent: true,
            opacity: 0.5
        });

        const icosahedronMesh = new THREE.Mesh(geometry, material);
        scene.add(icosahedronMesh);

        const animate = () => {
            requestAnimationFrame(animate);
            icosahedronMesh.rotation.x += 0.001;
            icosahedronMesh.rotation.y += 0.0015;
            renderer.render(scene, camera);
        };

        const onResize = () => {
            if (container && camera && renderer) {
                camera.aspect = container.offsetWidth / container.offsetHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(container.offsetWidth, container.offsetHeight);
            }
        };

        window.addEventListener('resize', onResize, false);
        animate();
    };
*/