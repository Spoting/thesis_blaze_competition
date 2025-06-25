// This function will initialize the competition list logic
export function initializeCompetitionList(mercurePublicUrl, publicStatuses, statusLabels) {
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize active status to the first public status
        let activeStatus = publicStatuses[0];

        // --- Tab Switching Logic ---
        const tabButtons = document.querySelectorAll('#competition-tabs .tab-button');
        const statusSections = document.querySelectorAll('#competition-content .status-section');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetStatus = button.dataset.status;

                // Deactivate currently active tab and its content section
                const currentActiveTab = document.querySelector('.tab-button.bg-blue-600');
                if (currentActiveTab) {
                    currentActiveTab.classList.remove('bg-blue-600', 'text-white', 'shadow-md');
                    currentActiveTab.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-blue-100', 'hover:text-blue-700');
                }
                const currentActiveSection = document.querySelector('.status-section:not(.hidden)');
                if (currentActiveSection) {
                    currentActiveSection.classList.add('hidden');
                }

                // Activate the clicked tab and its corresponding content section
                button.classList.add('bg-blue-600', 'text-white', 'shadow-md');
                button.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-blue-100', 'hover:text-blue-700');
                document.getElementById(`competitions-${targetStatus}`).classList.remove('hidden');

                activeStatus = targetStatus; // Update the globally tracked active status
            });
        });

        // --- Sorting Logic for Each Tab ---
        document.querySelectorAll('.sort-select').forEach(select => {
            select.addEventListener('change', event => {
                const sortValue = event.target.value;
                const status = event.target.dataset.sortStatus;
                const container = document.getElementById(`competitions-${status}`);
                const cards = Array.from(container.querySelectorAll('.competition-card-wrapper'));

                // Determine sorting key and direction
                const [key, direction] = sortValue.split('-');
                const attr = key === 'start' ? 'data-start-date' : 'data-end-date';
                const asc = direction === 'asc';

                cards.sort((a, b) => {
                    const dateA = new Date(a.getAttribute(attr));
                    const dateB = new Date(b.getAttribute(attr));
                    return asc ? dateA - dateB : dateB - dateA;
                });

                // Reorder cards in DOM
                cards.forEach(card => container.appendChild(card));
            });
        });


        // --- Mercure Dynamic Update Functions ---

        /**
         * Handles Mercure updates for a competition,
         * either adding, moving, or removing its card in the DOM.
         * @param {number} competitionId - The ID of the competition.
         * @param {string} newStatus - The new status of the competition.
         * @param {string|null} newHtml - The updated HTML for the competition card, or null if it should be removed.
         */
        const updateCompetitionCard = (competitionId, newStatus, newHtml) => {
            const competitionCardWrapperId = `competition-card-wrapper-${competitionId}`;
            let existingCardWrapper = document.getElementById(competitionCardWrapperId);

            // Determine the current status container this card is in (if it exists)
            const currentStatusOfCard = existingCardWrapper ? existingCardWrapper.closest('[data-status-container]')?.dataset.statusContainer : null;

            // Check if the new status is one that should be publicly displayed
            const isPublicStatus = publicStatuses.includes(newStatus);

            if (!isPublicStatus) {
                // Competition status is no longer public, remove the card if it exists in the DOM
                if (existingCardWrapper) {
                    existingCardWrapper.remove();
                    console.log(`Competition ID ${competitionId} removed (status changed to non-public).`);
                    updateStatusCount(currentStatusOfCard, -1); // Decrement count for the old status
                }
                return; // No further action if not public
            }

            // If status is public
            if (existingCardWrapper) {
                // Card already exists in the DOM
                if (currentStatusOfCard === newStatus) {
                    // Status is the same, only update its HTML content
                    existingCardWrapper.innerHTML = newHtml;
                    console.log(`Competition ID ${competitionId} HTML updated in same section.`);
                } else {
                    // Status changed, move the card to the new status section
                    const oldStatusContainer = document.getElementById(`competitions-${currentStatusOfCard}`);
                    const newStatusContainer = document.getElementById(`competitions-${newStatus}`);

                    if (oldStatusContainer && newStatusContainer) {
                        oldStatusContainer.removeChild(existingCardWrapper); // Remove from old section
                        newStatusContainer.appendChild(existingCardWrapper);  // Add to new section
                        existingCardWrapper.innerHTML = newHtml; // Update HTML after moving
                        console.log(`Competition ID ${competitionId} moved from ${currentStatusOfCard} to ${newStatus}.`);

                        updateStatusCount(currentStatusOfCard, -1); // Decrement count for old status
                        updateStatusCount(newStatus, 1);             // Increment count for new status
                    } else {
                        console.warn(`Could not find old or new status container for Competition ID ${competitionId}. Cannot move card.`);
                    }
                }
            } else {
                // New competition (card doesn't exist yet), create and append it
                const newStatusContainer = document.getElementById(`competitions-${newStatus}`);
                if (newStatusContainer) {
                    const newCardWrapper = document.createElement('div');
                    newCardWrapper.id = competitionCardWrapperId;
                    newCardWrapper.classList.add('competition-card-wrapper'); // Add the class for consistent styling
                    newCardWrapper.innerHTML = newHtml;
                    newStatusContainer.appendChild(newCardWrapper);
                    console.log(`New Competition ID ${competitionId} added to ${newStatus}.`);
                    updateStatusCount(newStatus, 1); // Increment count for the new status
                } else {
                    console.warn(`Could not find container for new status '${newStatus}'. Cannot add new card.`);
                }
            }

            // After any change (add/move/remove), update the 'no competitions' messages for affected sections
            updateNoCompetitionsMessage(currentStatusOfCard);
            updateNoCompetitionsMessage(newStatus);
        };

        /**
         * Updates the competition count displayed in the tab button.
         * @param {string} status - The status key (e.g., 'running').
         * @param {number} change - The amount to change the count by (e.g., 1 for add, -1 for remove).
         */
        const updateStatusCount = (status, change) => {
            if (!status) return; // Guard against null/undefined status
            const countSpan = document.getElementById(`count-${status}`);
            if (countSpan) {
                let currentCount = parseInt(countSpan.textContent, 10);
                countSpan.textContent = Math.max(0, currentCount + change); // Ensure count doesn't go below zero
            }
        };

        /**
         * Shows or hides the "No competitions currently in this status" message.
         * @param {string} status - The status key of the section to check.
         */
        const updateNoCompetitionsMessage = (status) => {
            if (!status) return; // Guard against null/undefined status
            const container = document.getElementById(`competitions-${status}`);
            if (container) {
                const cardsInContainer = container.querySelectorAll('.competition-card-wrapper').length;
                let messageElement = container.querySelector('.no-competitions-message');

                if (cardsInContainer === 0) {
                    if (!messageElement) {
                        // If no cards and no message, create and add it
                        messageElement = document.createElement('p');
                        messageElement.classList.add('col-span-full', 'text-center', 'text-gray-600', 'text-lg', 'py-8', 'no-competitions-message');
                        // messageElement.textContent = `No competitions currently in the "${statusLabels[status]|default(status)|capitalize}" status.`;
                        messageElement.textContent = `No competitions currently in the "${statusLabels[status] ?? status}" status.`;
                        container.appendChild(messageElement);
                    }
                } else {
                    // If cards exist and a message is present, remove the message
                    if (messageElement) {
                        messageElement.remove();
                    }
                }
            }
        };

        // --- Mercure Subscription ---
        const generalCompetitionTopic = '/competitions'; // This must match the topic in your Symfony controller's publishCompetitionUpdate
        const eventSource = new EventSource(`${mercurePublicUrl}?topic=${encodeURIComponent(generalCompetitionTopic)}`);
        console.log(`Subscribing to Mercure topic: ${generalCompetitionTopic} at ${mercurePublicUrl}`);
        eventSource.onmessage = event => {
            const data = JSON.parse(event.data);
            console.log(`Mercure general update received:`, data);
            updateCompetitionCard(data.id, data.status, data.html);
        };

        eventSource.onerror = error => {
            console.error(`Mercure EventSource general error:`, error);
            eventSource.close();
            // In a production app, you might add a retry mechanism with exponential backoff here.
        };
    });
}