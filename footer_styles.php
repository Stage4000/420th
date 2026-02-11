<?php
/**
 * Footer Styles Template
 * 
 * This file should be included within a <style> block in the parent document.
 * It contains CSS rules for the footer component.
 * 
 * Usage: <?php include 'footer_styles.php'; ?>
 */
?>
footer {
    background: #1a1f2e;
    color: #8b92a8;
    padding: 1.5rem 2rem;
    margin-top: auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #2a3142;
    font-size: 0.9rem;
}

footer a {
    color: #667eea;
    text-decoration: none;
    transition: color 0.3s;
}

footer a:hover {
    color: #8b9cff;
}

@media (max-width: 768px) {
    footer {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
        padding: 1rem;
        font-size: 0.8rem;
    }
}
