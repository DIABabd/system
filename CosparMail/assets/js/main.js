/**
 * COSPAR Mail System - Main JavaScript File
 * 
 * This file imports all module-specific JavaScript files
 * It should be the only script directly included in the HTML (it was not working so i included all the html files manually in the index.php)
 */

// Load the JavaScript modules in the correct order
document.addEventListener("DOMContentLoaded", function() {
    console.log("Loading JavaScript modules...");
    
    // Create and append script elements
    const scriptFiles = [
        'author-management.js',
        'email-ui.js',
        'email-operations.js',
        'popup-handlers.js',
        'initialization.js'
    ];
    
    // Function to load scripts sequentially
    function loadScripts(index) {
        if (index >= scriptFiles.length) {
            console.log("All scripts loaded successfully");
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'assets/js/' + scriptFiles[index];
        console.log(`Loading script: ${script.src}`);
        
        script.onload = function() {
            console.log(`Script loaded: ${scriptFiles[index]}`);
            loadScripts(index + 1);
        };
        
        script.onerror = function() {
            console.error(`Failed to load script: ${scriptFiles[index]}`);
            loadScripts(index + 1);
        };
        
        document.body.appendChild(script);
    }
    
    // Start loading scripts
    loadScripts(0);
});