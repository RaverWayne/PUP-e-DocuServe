TCPDF LIBRARY INSTALLATION INSTRUCTIONS
=========================================

To enable PDF export functionality, you need to download the TCPDF library:

1. Download TCPDF from: https://github.com/tecnickcom/TCPDF/releases
   - Download the latest release (e.g., tcpdf_6_6_5.zip)

2. Extract the downloaded ZIP file

3. Copy the contents to this directory:
   c:\wamp64\www\edocuserve\includes\tcpdf\
   
   The main file should be at:
   c:\wamp64\www\edocuserve\includes\tcpdf\tcpdf.php

4. After installation, the PDF export buttons in SuperAdmin > Export Data will work.

Alternative - Quick Installation:
If you have access to the command line and composer, you can also:
- Navigate to c:\wamp64\www\edocuserve
- Run: composer require tecnickcom/tcpdf

The system will automatically detect and use the library.
