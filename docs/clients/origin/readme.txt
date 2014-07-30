To associate TDMS files with origin and automatically open when new file in 
TDMS format is downloaded from ADEI:
 1. Associate any TDMS file with Origin: double click on the file, use "select
 the program for a list" menu entry and, then, browse for Origin8.exe
 2. In the explorer folder open "Tools/Folder Options", goto the "File Types"
 tab and select TDMS entry. Click on "Advanced" button and edit "open" action.
 3. Replace 
    "C:\Program Files\OriginLab\Origin8\Origin8.exe" %1
 with
    "C:\Program Files\OriginLab\Origin8\Origin8.exe" -R (impNITDM fname:="%1")
 (the path may differ)
 4. Press Ok/Ok. All done, from now the TDMS files would be opened in Origin8.
 
 