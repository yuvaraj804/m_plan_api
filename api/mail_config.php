<?php

// Mail configuration - update credentials and recipients as needed
// For security, consider loading these from environment variables or a secure secret store.

const MAIL_HOST = 'mail.minervasoft.net';
const MAIL_PORT = 465;
const MAIL_SECURE = 'ssl'; // 'ssl' or 'tls' or ''
const MAIL_USERNAME = 'noreply@minervasoft.net';
const MAIL_PASSWORD = 'NOreply2016';
const MAIL_FROM = 'noreply@minervasoft.net';
const MAIL_FROM_NAME = 'Minerva ERP';
// Comma-separated list of recipients
const MAIL_TO = 'internal@minervasoft.in';

// Optional: BCC, CC
const MAIL_BCC = '';
const MAIL_CC = '';

// Optional default subject prefix
const MAIL_SUBJECT_PREFIX = '[MINT] ';


function getMailRecipients() {
    $toList = array_filter(array_map('trim', explode(',', MAIL_TO)));
    return $toList;
}
