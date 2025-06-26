/**
 * Initializes Mercure subscription for global announcements and updates the DOM in real-time.
 * This function is designed to be reusable across any page displaying the #global-announcements fragment.
 *
 * @param {string} mercurePublicUrl - The public URL of the Mercure hub (e.g., 'http://localhost:3000/.well-known/mercure').
 * @param {string} announcementTopic - The specific Mercure topic for global announcements (e.g., 'http://yourdomain.com/.well-known/mercure/announcements').
 * @param {number} [maxDisplayedAnnouncements=12] - The maximum number of announcements to display in the UI.
 */
export function initializeAnnouncementsMercure(mercurePublicUrl, announcementTopic = '/global_announcements', maxDisplayedAnnouncements = 8) {
    document.addEventListener('DOMContentLoaded', () => {
        // const announcementsList = document.getElementById('announcements-list');
        const globalAnnouncementsSection = document.getElementById('global-announcements');

        // Exit if the announcement section isn't present on the page.
        if (!globalAnnouncementsSection) {
            console.warn('Global announcements section (id="global-announcements") not found. Mercure announcements will not be displayed on this page.');
            return;
        }

        const eventSource = new EventSource(`${mercurePublicUrl}?topic=${encodeURIComponent(announcementTopic)}`);


        console.log(`Subscribing to Mercure topic: ${announcementTopic} at ${mercurePublicUrl}`);

        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            console.log('Mercure Announcement update received:', data);

            // Only process updates identified as 'new_announcement' and containing a message.
            // data.type === 'new_announcement' && 
            if (data.message) {
                // let currentAnnouncementsList = announcementsList;
                let currentAnnouncementsList = document.getElementById('announcements-list');

                // If "No announcements yet." message is present, remove it.
                const noAnnouncementsMessage = document.querySelector('#global-announcements .no-announcements-message');
                if (noAnnouncementsMessage) {
                    noAnnouncementsMessage.remove();
                }
                
                // If the UL element for announcements doesn't exist (e.g., initially no announcements were rendered), create it.
                if (!currentAnnouncementsList) {
                    globalAnnouncementsSection.innerHTML = `
                        <h4 class="text-xl font-semibold text-gray-800 mb-3 border-b pb-2 border-gray-200">Latest Announcements</h4>
                        <ul id="announcements-list" class="list-none p-0 m-0 grid grid-cols-1 gap-y-3"></ul>
                    `;
                    currentAnnouncementsList = document.getElementById('announcements-list');
                }

                // Define the styling map directly in JS to match Twig's logic.
                // This ensures consistency for dynamically added announcements.
                const statusStyles = {
                    'scheduled': { 'icon': 'fa-solid fa-calendar-alt', 'bg': 'bg-blue-100', 'text': 'text-blue-800', 'border': 'border-blue-200' },
                    'running': { 'icon': 'fa-solid fa-play-circle', 'bg': 'bg-green-100', 'text': 'text-green-800', 'border': 'border-green-200' },
                    'submissions_ended': { 'icon': 'fa-solid fa-hourglass-end', 'bg': 'bg-orange-100', 'text': 'text-orange-800', 'border': 'border-orange-200' },
                    'winners_announced': { 'icon': 'fa-solid fa-trophy', 'bg': 'bg-yellow-100', 'text': 'text-yellow-800', 'border': 'border-yellow-200' },
                    'cancelled': { 'icon': 'fa-solid fa-circle-xmark', 'bg': 'bg-red-100', 'text': 'text-red-800', 'border': 'border-red-200' },
                    'default': { 'icon': 'fa-solid fa-info-circle', 'bg': 'bg-gray-100', 'text': 'text-gray-700', 'border': 'border-gray-200' }
                };
                const currentStatus = data.status || 'default';
                const style = statusStyles[currentStatus] || statusStyles.default;

                // Create the new list item element.
                const newItem = document.createElement('li');
                newItem.setAttribute('data-timestamp', data.timestamp);
                newItem.setAttribute('data-status', currentStatus);
                newItem.classList.add(
                    'p-3', 'rounded-md', 'flex', 'items-start', 'gap-3', 'text-sm', 'leading-tight',
                    'transition-all', 'duration-800', 'ease-in-out', 'border', 'hover:bg-opacity-80', 
                    style.bg, style.text, style.border
                );

                // Populate the inner HTML of the new list item.
                newItem.innerHTML = `
                    <span class="flex-shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-full ${style.text} text-white">
                         <i class="${style.icon} text-blue-500 mr-2"></i>
                    </span>
                    <div class="flex-grow flex flex-col">
                        <strong class="font-semibold">${data.message}</strong>
                        <small class="text-gray-500 text-xs mt-0.5">(${new Date(data.timestamp).toLocaleString()})</small>
                    </div>
                `;

                // Add the new announcement to the top of the list.
                currentAnnouncementsList.prepend(newItem);

                // If the list is now long enough, ensure it switches to a two-column layout if it hasn't already.
                // This class application should match the logic in your Twig template.
                const totalAnnouncements = currentAnnouncementsList.children.length;
                if (totalAnnouncements > 5 && !currentAnnouncementsList.classList.contains('md:grid-cols-2')) {
                    currentAnnouncementsList.classList.add('md:grid-cols-2', 'md:gap-x-4');
                }

                // Trim the displayed list to the maximum allowed announcements.
                while (currentAnnouncementsList.children.length > maxDisplayedAnnouncements) {
                    currentAnnouncementsList.removeChild(currentAnnouncementsList.lastChild);
                }
            }
        };

        eventSource.onerror = function(error) {
            console.error('Mercure Announcement EventSource failed:', error);
            eventSource.close();
            // For production, consider implementing a reconnection strategy with exponential backoff.
        };
    });
}