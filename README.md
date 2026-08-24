# 🛡️ Yong_arknight (Rhodes Island HR Management)

A web-based Arknights operator gallery and personnel database system built with PHP, PDO, and MySQL, featuring dynamic phase promotion toggles, attribute tracking, combat skills configurations, and image zooming.

---

## ✨ Key Features

* **Operator Gallery & Search**: Browse all recruited operators, filter by class (Vanguard, Guard, Sniper, Defender, Medic, Caster, Supporter, Specialist), or search by name.
* **Phase Promotion Toggling**: Switch between Phase 0/1 and Elite 2 stats and portraits dynamically on an operator's dossier page.
* **Interactive Image Zooming**: Zoom in/out via mouse wheel or click-to-zoom on operator portraits.
* **Recruitment & Modification**: Add new operators or update existing dossiers, including combat skills (up to 3 skills), base/Elite 2 attributes, and lore/archive data.
* **Dismissal System**: Safely remove operator files from the database archive with confirmation prompts.

---

## 📂 System File Directory

| Path / File | Description |
| :--- | :--- |
| **index.php** | Main operator gallery dashboard with search, filtering, and rarity cards. |
| **add.php** | Recruitment form page to add new operators into the database. |
| **details.php** | Detailed operator dossier with attribute tables, skill lists, and Phase 0/E2 switcher. |
| **edit.php** | Data modification form to update operator attributes, skills, and lore. |
| **delete.php** | Backend handler to safely remove/dismiss an operator record. |
| **db.php** | Secure PDO database connection configuration file. |
| **style.css** | Custom dark-themed Arknights UI stylesheet with grid galleries and animations. |

---

## 🛠️ Tech Stack

* **Backend**: PHP 8+ (with secure PDO database operations)
* **Database**: MySQL / MariaDB (arknights_db)
* **Frontend**: HTML5, CSS3 (Flexbox/Grid), and JavaScript

---

## ⚙️ Step-by-Step Guide: How to Use This Project

### Step 1: Local Environment Setup
1. Clone or place your repository inside your local server directory (such as XAMPP's htdocs folder).
2. Start Apache and MySQL services via your XAMPP control panel.

### Step 2: Set Up the Database
1. Open your browser and go to phpMyAdmin (http://localhost/phpmyadmin/).
2. Create a new database named **arknights_db**.
3. Import the provided SQL dump to automatically create the operators table and load initial operator data.
4. Verify your database credentials in **db.php** if needed:
   $host = 'localhost';
   $dbname = 'arknights_db';
   $user = 'root'; 
   $pass = '';     

### Step 3: Accessing the Gallery
1. Open your web browser and navigate to the project root directory:
   http://localhost/Yong_arknight/index.php
2. You will see the main **Rhodes Island Archives** gallery showcasing cards color-coded by rarity with E2 indicator tags.

### Step 4: Searching and Filtering Operators
1. Use the search bar at the top of the gallery to look up operators by their name.
2. Select a specific class from the dropdown menu (e.g., GUARD, SNIPER, MEDIC) to filter results.
3. Click **RESET** to clear filters and view all operators.

### Step 5: Viewing and Interacting with Operator Dossiers
1. Click on any operator card in the gallery to open their detailed dossier page (details.php).
2. Use the **PROMOTION** toggle buttons to switch between **PHASE 0/1** and **PHASE 2** stats/portraits.
3. Scroll over the operator portrait and use your mouse wheel or click to zoom in/out of the artwork.
4. Review combat skill details, icons, and archive background lore.

### Step 6: Recruiting New Operators
1. Click the **+ RECRUIT OPERATOR** button on the top right of the main gallery (index.php).
2. Fill out the Core Profile (Name, Rarity, Class), Base attributes/images, optional Elite 2 attributes/images, combat skills, and archive lore in add.php.
3. Click **Confirm Recruitment** to archive the new operator into the database.

### Step 7: Modifying or Dismissing Operator Records
* **Modify Data**: While viewing an operator's dossier (details.php), click the **MODIFY DATA** button to edit existing stats, skills, or images in edit.php.
* **Dismiss Operator**: Click the **[ DISMISS OPERATOR ]** link at the bottom of any operator card in the gallery to permanently remove them from the records with a confirmation check.

---

## 📄 License

This project is open-source and available under the MIT License.
