<?php
/**
 * Navbar Styles Template
 * 
 * This file should be included within a <style> block in the parent document.
 * It contains CSS rules for the navigation bar component.
 * 
 * Usage: <?php include 'navbar_styles.php'; ?>
 */
?>
.navbar {
        background: #1a1f2e;
        color: white;
        padding: 1rem 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #2a3142;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .navbar-logo {
        height: 40px;
        width: auto;
    }
    
    .navbar-title {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .navbar-links {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .navbar-links a {
        color: #e4e6eb;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        transition: all 0.3s;
        border: 1px solid transparent;
    }
    
    .navbar-links a:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
    }
    
    .navbar-links a.active {
        background: rgba(102, 126, 234, 0.2);
        border-color: #667eea;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid #4a5568;
    }
    
    .logout-btn {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        text-decoration: none;
        transition: all 0.3s;
        border: 1px solid transparent;
    }
    
    .logout-btn:hover {
        background: rgba(255, 107, 107, 0.2);
        border-color: #ff6b6b;
    }
    
    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }
    
    /* Responsive Styles */
    @media (max-width: 768px) {
        .navbar {
            flex-wrap: wrap;
            padding: 1rem;
        }
        
        .mobile-menu-toggle {
            display: block;
        }
        
        .navbar-links {
            display: none;
            width: 100%;
            flex-direction: column;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #2a3142;
        }
        
        .navbar-links.active {
            display: flex;
        }
        
        .navbar-links a {
            width: 100%;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .navbar-brand {
            font-size: 1.25rem;
        }
        
        .navbar-logo {
            height: 30px;
        }
        
        .user-avatar {
            display: none;
        }
    }
