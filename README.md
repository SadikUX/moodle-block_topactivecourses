# Top Active Courses Block for Moodle

## Overview

The **Top Active Courses** block is a Moodle plugin that displays a visually appealing list of the most active courses on your Moodle site. It is designed to help users discover popular courses they are not yet enrolled in, but where self-enrolment is possible. The block uses a modern card-based layout with course images, titles, and direct links to the course pages.

## Features

- **Shows the most active courses** from the last 7 days, based on user interactions (log entries).
- **Excludes courses** in which the current user is already enrolled.
- **Only displays courses** where self-enrolment is enabled and available.
- **Modern, responsive card layout** with course images, titles, and links.
- **Customizable appearance** via the included `styles.css`.
- **No personal data is stored** by the block (GDPR compliant).

## How It Works

- The block queries the Moodle logstore to find courses with the highest number of unique user interactions in the last 7 days.
- For each course, it checks if the current user is not enrolled and if self-enrolment is enabled.
- The block then displays up to 10 of these courses as cards, each showing the course image, title, and a link to the course page.

## Installation

1. Copy the `topactivecourses` folder into your Moodle site's `blocks` directory.
2. Visit the **Site administration > Notifications** page to complete the installation.
3. Add the block to your dashboard or course pages as desired.

## Capabilities

- `block/topactivecourses:addinstance` — Allows a user to add the block to a course page (default: editingteacher, manager).
- `block/topactivecourses:myaddinstance` — Allows a user to add the block to their dashboard (default: all users).

## Privacy

This block does **not** store any personal data. See `classes/privacy/provider.php` for details.

## Author

- **Sadik Mert**  
  Email: sadikmert@hotmail.de

## License

This plugin is licensed under the GNU General Public License v3.0. See the `LICENSE` file for details.