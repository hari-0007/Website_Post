// Make reCAPTCHA callback globally available
window.onloadRecaptchaCallback = function() {
    const feedbackContainer = document.getElementById('feedbackRecaptchaContainer');
    if (feedbackContainer && serverData.recaptchaSiteKey) {
        window.state = window.state || {};
        window.state.feedbackRecaptchaId = grecaptcha.render(feedbackContainer, {
            'sitekey': serverData.recaptchaSiteKey
        });
    }
    const reportContainer = document.getElementById('reportRecaptchaContainer');
    if (reportContainer && serverData.recaptchaSiteKey) {
        window.state = window.state || {};
        window.state.reportRecaptchaId = grecaptcha.render(reportContainer, {
            'sitekey': serverData.recaptchaSiteKey
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------------------
    // STATE & CONFIG
    // ------------------------------------------------------------------------
    const state = window.state || {
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

    // ------------------------------------------------------------------------
    // DOM ELEMENTS
    // ------------------------------------------------------------------------
    const elements = {
        jobListingsContainer: document.getElementById('job-listings-container'),
        mainContainer: document.querySelector('.container'),
        authModal: document.getElementById('authModal'),
        shareModal: document.getElementById('jobShareModal'),
        cautionModal: document.getElementById('jobCautionModal'),
        reportFormContainer: document.getElementById('reportJobFormContainer'),
        reportedJobInfo: document.getElementById('reportedJobInfo'),
        reportIssueBtn: document.getElementById('reportIssueBtn'),
        cautionUnderstoodBtn: document.getElementById('cautionUnderstoodBtn'),
        reportStatusMessage: document.getElementById('reportStatusMessage'),
        joinChannelsPopup: document.getElementById('joinChannelsPopup'),
        searchForm: document.querySelector('.search-bar form'),
        loginForm: document.getElementById('loginForm'),
        registerForm: document.getElementById('registerForm'),
        feedbackForm: document.getElementById('feedbackForm'),
        reportForm: document.getElementById('reportForm'),
        cookieBanner: document.getElementById('cookieConsentBanner'),
        menuToggle: document.getElementById('menuToggle'),
        mobileNav: document.getElementById('mobileNav'),
        mobileNavOverlay: document.getElementById('mobileNavOverlay'),
        closeMenuBtn: document.querySelector('.close-menu-btn'),
        sidebar: document.querySelector('.sidebar'),
        mobileFilters: document.querySelector('.mobile-filters'),
    };

    // ------------------------------------------------------------------------
    // HELPER FUNCTIONS
    // ------------------------------------------------------------------------
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

    // ------------------------------------------------------------------------
    // CORE LOGIC
    // ------------------------------------------------------------------------
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
                updateSidebarCounts();
                elements.jobListingsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(error => {
                console.error('Error fetching jobs:', error);
                elements.jobListingsContainer.innerHTML = '<p class="no-jobs-message">Error loading jobs. Please try again.</p>';
                updateActiveFilterLinks(url);
            })
            .finally(() => {
                elements.jobListingsContainer.classList.remove('loading-content');
            });
    };

    const toggleJobDetails = (jobCard) => {
        const details = jobCard.querySelector('.job-details');
        const summary = jobCard.querySelector('.job-summary');
        if (!details || !summary) return;
        const isCurrentlyCollapsed = details.style.display === 'none';
        document.querySelectorAll('.job-card.job-card-active').forEach(activeCard => {
            if (activeCard !== jobCard) {
                activeCard.querySelector('.job-details').style.display = 'none';
                activeCard.querySelector('.job-summary').style.display = '';
                activeCard.classList.remove('job-card-active');
            }
        });
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
        fetch('increment_job_view.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_id: jobId })
        }).catch(console.error);

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

const updateSidebarCounts = () => {
    const counts = {
        All: allJobs.length,
        Remote: allJobs.filter(j => (j.type || '').toLowerCase() === 'remote').length,
        Onsite: allJobs.filter(j => (j.type || '').toLowerCase() === 'onsite').length,
        Hybrid: allJobs.filter(j => (j.type || '').toLowerCase() === 'hybrid').length,
        FullTime: allJobs.filter(j => (j.type || '').toLowerCase() === 'full time').length,
        PartTime: allJobs.filter(j => (j.type || '').toLowerCase() === 'part time').length,
        Internship: allJobs.filter(j => (j.type || '').toLowerCase() === 'internship').length,
        Developer: allJobs.filter(j => (j.type || '').toLowerCase() === 'developer').length,
        Rotation: allJobs.filter(j => (j.type || '').toLowerCase() === 'rotation').length,
        Short_Term: allJobs.filter(j => (j.type || '').toLowerCase() === 'short term').length,
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

// Make sure updateSidebarCounts() is called after every AJAX navigation and on page load

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
                if (linkType === currentType && linkFilter === currentFilter) {
                    isActive = true;
                }
            }
            link.classList.toggle('active-filter', isActive);
        });
    };

    // ------------------------------------------------------------------------
    // MODAL & POPUP LOGIC
    // ------------------------------------------------------------------------
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
    window.openAuthModal = openAuthModal;

    const closeAuthModal = () => {
        if (elements.authModal) elements.authModal.style.display = 'none';
        unlockBackground();
    };
    window.closeAuthModal = closeAuthModal;

    window.showLoginView = (e) => { e.preventDefault(); openAuthModal('login'); };
    window.showRegisterView = (e) => { e.preventDefault(); openAuthModal('register'); };

    const openMobileMenu = () => {
        document.body.classList.add('mobile-nav-open');
        lockBackground();
    };
    const closeMobileMenu = () => {
        document.body.classList.remove('mobile-nav-open');
        unlockBackground();
    };
    window.closeMobileMenu = closeMobileMenu;

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
        elements.shareModal.querySelector('#shareViaEmail').href = `mailto:?subject=${encodedText}&body=${encodedText}%0A%0A${encodedUrl}`;
        lockBackground();
        elements.shareModal.style.display = 'flex';
    };

    const closeShareModal = () => {
        if (elements.shareModal) {
            elements.shareModal.style.display = 'none';
            const feedbackEl = elements.shareModal.querySelector('#copyLinkFeedback');
            if (feedbackEl) feedbackEl.textContent = '';
        }
        unlockBackground();
    };

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
        // Check if popup was already shown in this session
        const popupShown = getCookie(config.joinPopupCookieName);
        
        if (elements.joinChannelsPopup && !popupShown) {
            // Show popup after 1.5 seconds
            setTimeout(() => {
                lockBackground();
                elements.joinChannelsPopup.style.display = 'flex';
                console.log('WhatsApp popup shown'); // Debug log
            }, 1500);
        } else if (!elements.joinChannelsPopup) {
            console.warn('joinChannelsPopup element not found in DOM');
        }
    };

    const closeJoinChannelsPopup = () => {
        if (elements.joinChannelsPopup) {
            elements.joinChannelsPopup.style.display = 'none';
            unlockBackground();
        }
        // Set cookie so popup doesn't show again for 1 day
        setCookie(config.joinPopupCookieName, 'true', 1);
    };
    window.closeJoinChannelsPopup = closeJoinChannelsPopup;
    window.handleJoinChannelsClick = closeJoinChannelsPopup;

    window.openWModal = () => {
        document.getElementById('modal').style.display = 'flex';
        lockBackground();
    };
    window.closeWModal = () => {
        document.getElementById('modal').style.display = 'none';
        unlockBackground();
    };

    // ------------------------------------------------------------------------
    // EVENT LISTENERS & BINDINGS
    // ------------------------------------------------------------------------
    if (elements.menuToggle) elements.menuToggle.addEventListener('click', openMobileMenu);
    if (elements.closeMenuBtn) elements.closeMenuBtn.addEventListener('click', closeMobileMenu);
    if (elements.mobileNavOverlay) elements.mobileNavOverlay.addEventListener('click', closeMobileMenu);

    // --- Feedback Form Logic ---
    const showFeedbackExtras = () => {
        const starsContainer = document.getElementById('feedbackStarsContainer');
        const recaptchaContainer = document.getElementById('feedbackRecaptchaContainer');
        if (starsContainer) starsContainer.style.display = 'block';
        if (recaptchaContainer) recaptchaContainer.style.display = 'block';
    };

    const resetFeedbackForm = () => {
        const starsContainer = document.getElementById('feedbackStarsContainer');
        const recaptchaContainer = document.getElementById('feedbackRecaptchaContainer');
        const feedbackTextarea = elements.feedbackForm?.querySelector('textarea[name="message"]');
        const ratingInput = document.getElementById('feedbackRatingInput');
        const stars = document.querySelectorAll('#feedbackStarRating span');
        if (elements.feedbackForm) elements.feedbackForm.reset();
        if (starsContainer) starsContainer.style.display = 'none';
        if (recaptchaContainer) recaptchaContainer.style.display = 'none';
        if (ratingInput) ratingInput.value = '0';
        stars.forEach(s => s.classList.remove('selected', 'hover'));
        if (state.feedbackRecaptchaId !== null) {
            grecaptcha.reset(state.feedbackRecaptchaId);
        }
        if (feedbackTextarea) {
            feedbackTextarea.addEventListener('input', showFeedbackExtras, { once: true });
        }
    };

    const feedbackTextarea = elements.feedbackForm?.querySelector('textarea[name="message"]');
    if (feedbackTextarea) {
        feedbackTextarea.addEventListener('input', showFeedbackExtras, { once: true });
    }

    // Star Rating Click/Hover Logic
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
            if (jobCard.classList.contains('ad-card')) {

               return;
            }

            const shareBtn = e.target.closest('.share-button');
            const applyBtn = e.target.closest('.apply-button');
            const cautionBtn = e.target.closest('.job-caution-alert');
            const contactInfoContainer = e.target.closest('.show-email-addresses, .show-phone-numbers');

            // If any of these buttons were clicked, handle and return
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
                }
                return;
            }
            if (cautionBtn) {
                e.stopPropagation();
                showCautionAlert(jobCard);
                return;
            }
            if (contactInfoContainer) {
                // --- FIX: Robustly find the info span for both phone and email ---
                const type = contactInfoContainer.classList.contains('show-phone-numbers') ? 'phone' : 'email';
                let infoSpan = null;
                // Try both next and previous siblings for robustness
                if (type === 'phone') {
                    infoSpan = contactInfoContainer.previousElementSibling;
                    if (!infoSpan || !infoSpan.classList.contains('phone-numbers')) {
                        infoSpan = contactInfoContainer.nextElementSibling;
                    }
                } else {
                    infoSpan = contactInfoContainer.nextElementSibling;
                    if (!infoSpan || !infoSpan.classList.contains('email-addresses')) {
                        infoSpan = contactInfoContainer.previousElementSibling;
                    }
                }
                const infoClass = type === 'phone' ? 'phone-numbers' : 'email-addresses';
                if (infoSpan && infoSpan.classList.contains(infoClass)) {
                    // Use the correct job object and info
                    const jobId = jobCard.dataset.jobId;
                    const job = allJobs.find(j => j.id == jobId);
                    const contactInfo = job ? (job[type + 's'] || job[type]) : null;
                    if (contactInfo) {

                            infoSpan.innerHTML = contactInfo;
                            infoSpan.style.display = 'inline';
                            infoSpan.innerHTML = infoSpan.innerHTML.replace(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/g, '<a href="mailto:$1">$1</a>').replace(/((\+?\d{2}-?)|0)?(\d{7,8})/g, '<a href="tel:$1$3">$1$3</a>');
                            contactInfoContainer.style.display = 'none';
                    } else {
                        contactInfoContainer.textContent = 'Not available';
                        contactInfoContainer.style.color = 'red';
                        setTimeout(() => {
                            contactInfoContainer.textContent = `🔒 Show ${type === 'phone' ? 'Numbers' : 'Emails'}`;
                            contactInfoContainer.style.color = '';
                        }, 2000);
                    }



                        /*infoSpan.innerHTML = contactInfo;
                        infoSpan.style.display = 'inline';
                        infoSpan.innerHTML = infoSpan.innerHTML.replace(/([a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/g,'<a href="mailto:$1">$1</a>').replace(/((\+?\d{2}-?)|0)?(\d{7,8})/g,'<a href="tel:$1$3">$1$3</a>');
                        contactInfoContainer.style.display = 'none';
                    } else {
                        contactInfoContainer.textContent = 'Not available';
                        contactInfoContainer.style.color = 'red';
                        setTimeout(() => {
                            contactInfoContainer.textContent = `🔒 Show ${type === 'phone' ? 'Numbers' : 'Emails'}`;
                            contactInfoContainer.style.color = '';
                        }, 2000);
                    }*/
                }
                return;
            }

           // --- Default Job Card Click (Toggle Details) ---
           const isTitleClick = e.target.closest('h3');
           const isCompanyLocationClick = e.target.closest('.job-card-company-location');
           const isSummaryClick = e.target.closest('.job-summary');

           if (isTitleClick || isCompanyLocationClick || isSummaryClick) {
                toggleJobDetails(jobCard);
                e.preventDefault(); // Prevent default action if needed
                return;
            }
        }

        // --- Share Modal Close Button ---
        if (e.target.matches('.share-modal-close-button')) {
            closeShareModal();
            return;
        }


        // --- Caution Modal Buttons (works anywhere in DOM) ---
        if (e.target.matches('#cautionUnderstoodBtn')) {
            e.preventDefault();
            if (e.target.textContent.trim().toLowerCase() === 'cancel') {
                resetReportForm();
            } else {
                closeCautionAlert();
            }
            return;
        }
        if (e.target.matches('#reportIssueBtn')) {
            e.preventDefault();
            if (e.target.textContent.trim().toLowerCase() === 'submit report') {
                submitReport();
            } else {
                toggleReportForm();
            }
            return;
        }

        // --- Filter & Pagination Links ---
        const navLink = e.target.closest('.sidebar a, .mobile-filters a, .pagination-container a:not(.disabled)');
        if (navLink) {
            e.preventDefault();
            handleAjaxNavigation(navLink.href);
            return;
        }

        // --- Close Modal on Overlay Click ---
        if (e.target.matches('.modal, .share-modal-overlay')) {
            if (e.target.id !== 'jobCautionModal') {
                e.target.style.display = 'none';
                unlockBackground();
            }
            return;
        }

        // --- Copy Link Button ---
        if (e.target.matches('#copyJobLinkButton')) {
            const urlToCopy = e.target.dataset.url;
            const feedbackEl = elements.shareModal.querySelector('#copyLinkFeedback');
            navigator.clipboard.writeText(urlToCopy).then(() => {
                feedbackEl.textContent = 'Link copied!';
                feedbackEl.style.color = 'green';
                setTimeout(closeShareModal, 1500);
            }).catch(err => {
                feedbackEl.textContent = 'Failed to copy.';
                feedbackEl.style.color = 'red';
                console.error('Failed to copy link:', err);
            });
            return;
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
            
            // Get action from form's action attribute (e.g., "register.php?action=register")
            const action = new URL(form.action, window.location.origin).searchParams.get('action');
            formData.append('action', action);
            
            errorMessageEl.style.display = 'none';
            
            fetch('register.php?action=' + action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error: ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    alert(action === 'login' ? 'Login successful!' : 'Registration successful!');
                    window.location.href = 'profile.php';
                } else {
                    errorMessageEl.textContent = data.message || 'An error occurred.';
                    errorMessageEl.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Auth error:', error);
                errorMessageEl.textContent = 'A network error occurred. Please try again.';
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
                        resetFeedbackForm();
                    } else {
                        grecaptcha.reset(state.feedbackRecaptchaId);
                    }
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

    // ------------------------------------------------------------------------
    // INITIALIZATION
    // ------------------------------------------------------------------------
    const init = () => {
        updateSidebarCounts();
        updateActiveFilterLinks(window.location.href);
        expandJobFromUrl();
        initJoinChannelsPopup();
       
    };
    init();
});
