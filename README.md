# Top Active Courses Block for Moodle

## Overview

The **Top Active Courses** block is a Moodle plugin that displays a visually appealing list of the most active courses on your Moodle site. It is designed to help users discover popular courses they are not yet enrolled in, but where self-enrolment is possible. The block uses a modern card-based layout with course images, titles, and direct links to the course pages.

## Features

- **Shows the most active courses** from the last 7 days, based on user interactions (log entries).
- **Excludes courses** in which the current user is already enrolled.
- **Only displays courses** where self-enrolment is enabled and available.
- **Modern, responsive card layout** with course images, titles, and links.
- **Customizable appearance** via the included `styles.css`.
- **No personal data is stored** by the block.

## How It Works

- The block queries the Moodle logstore to find courses with the highest number of unique user interactions in the last 7 days.
- For each course, it checks if the current user is not enrolled and if self-enrolment is enabled.
- The block then displays up to X (Default 10) of these courses as cards, each showing the course image, title, and a link to the course page.

## Installation

There are two ways to install the Top Active Courses block:

1. **Via Moodle Plugins Directory (recommended):**
   - Download and install the plugin directly from the Moodle admin interface under
     **Site administration > Plugins > Install plugins**.
   - Upload the ZIP file or install from the Moodle plugins repository.
   - Follow the on-screen instructions to complete installation.

2. **Manual Installation:**
   - Copy the `topactivecourses` folder into your Moodle site's `blocks` directory.
   - Visit **Site administration > Notifications** to complete the installation.
   - Add the block to your dashboard or course pages as desired.

## Configuration

The block provides two admin settings available under **Site administration > Plugins > Blocks > Top Active Courses**:

- **Top X courses**: Number of courses to display (default: 10).
- **Time range (days)**: Number of days to look back for user activity (default: 7).

These settings allow site admins to customize how many and how recent the displayed courses are.

## Capabilities

- `block/topactivecourses:addinstance` — Allows a user to add the block to a course page (default: editingteacher, manager).
- `block/topactivecourses:myaddinstance` — Allows a user to add the block to their dashboard (default: all users).

## Course Images

- The block attempts to display the course summary image.
- If no image is set for a course, a default placeholder image from Picsum is shown.

## How popularity is calculated

The block calculates course popularity by counting unique user interactions (log entries) in the Moodle logstore within the configured time range. Only courses with self-enrolment enabled and in which the user is not already enrolled are considered.

## Privacy

This block does **not** store any personal data. See `classes/privacy/provider.php` for details.

## Author

- **Sadik Mert**  
  E-Mail: sadikmert@hotmail.de

## License

This plugin is licensed under the GNU General Public License v3.0. See the `LICENSE` file for details.