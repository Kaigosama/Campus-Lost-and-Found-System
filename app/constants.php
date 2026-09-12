<?php
/**
 * Application-wide constants. Values that later need to be validated on the
 * server (categories, roles, statuses) live here so the forms and the future
 * backend share one source of truth.
 */

const APP_NAME      = 'CLAFS';
const APP_FULL_NAME = 'Campus Lost-and-Found System';

const CATEGORIES = [
    'Electronics',
    'IDs & Cards',
    'Bags',
    'Clothing',
    'Books & Notes',
    'Keys',
    'Accessories',
    'Other',
];

const ROLES = [
    'user'  => 'User',
    'staff' => 'Staff',
    'admin' => 'Administrator',
];

const LOST_STATUSES = [
    'open'    => 'Open',
    'matched' => 'Matched',
    'closed'  => 'Closed',
];

const FOUND_STATUSES = [
    'stored'   => 'In Storage',
    'returned' => 'Returned',
    'disposed' => 'Disposed',
];

const CLAIM_STATUSES = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

const ALLOWED_EMAIL_DOMAINS = ['mymail.mapua.edu.ph', 'mapua.edu.ph'];

/** Suggested values for the location <datalist>s on the forms. */
const CAMPUS_LOCATIONS = [
    'Library',
    'Cafeteria',
    'Gymnasium',
    'Student Lounge',
    'Parking Area',
    'North Building',
    'South Building',
    'Admin Building',
    'Chapel',
    'Covered Court',
];

const MAX_UPLOAD_MB = 5;
