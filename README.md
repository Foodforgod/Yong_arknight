# 🛡️ Yong_arknight (Rhodes Island HR Management)

A web-based Arknights operator gallery and personnel database system built with **PHP, PDO, and MySQL**, featuring dynamic phase promotion toggles, attribute tracking, combat skills configurations, and image zooming.

---

## ✨ Key Features

* **Operator Gallery & Search**: Browse all recruited operators, filter by class (`Vanguard`, `Guard`, `Sniper`, `Defender`, `Medic`, `Caster`, `Supporter`, `Specialist`), or search by name.
* **Phase Promotion Toggling**: Switch between Phase 0/1 and Elite 2 stats and portraits dynamically on an operator's dossier page.
* **Interactive Image Zooming**: Zoom in/out via mouse wheel or click-to-zoom on operator portraits.
* **Recruitment & Modification**: Add new operators or update existing dossiers, including combat skills (up to 3 skills), base/Elite 2 attributes, and lore/archive data[cite: 14, 19].
* **Dismissal System**: Safely remove operator files from the database archive with confirmation prompts.

---

## 📂 System File Directory

| Path / File | Description |
| :--- | :--- |
| **`index.php`** | Main operator gallery dashboard with search, filtering, and rarity cards. |
| **`add.php`** | Recruitment form page to add new operators into the database. |
| **`details.php`** | Detailed operator dossier with attribute tables, skill lists, and Phase 0/E2 switcher. |
| **`edit.php`** | Data modification form to update operator attributes, skills, and lore[cite: 19]. |
| **`delete.php`** | Backend handler to safely remove/dismiss an operator record[cite: 17]. |
| **`db.php`** | Secure PDO database connection configuration file[cite: 16]. |
| **`style.css`** | Custom dark-themed Arknights UI stylesheet with grid galleries and animations[cite: 22]. |

---

## 🛠️ Tech Stack

* **Backend**: PHP 8+ (with secure PDO database operations)[cite: 16]
* **Database**: MySQL / MariaDB (`arknights_db`)[cite: 15, 16]
* **Frontend**: HTML5, CSS3 (Flexbox/Grid), and JavaScript[cite: 14, 18, 22]

---

## ⚙️ Step-by-Step Guide: How to Use This Project

### Step 1: Local Environment Setup
1. Clone or place your repository inside your local server directory (such as XAMPP's `htdocs` folder)[cite: 15, 16].
2. Start Apache and MySQL services via your XAMPP control panel[cite: 15, 16].

### Step 2: Set Up the Database
1. Open your browser and go to phpMyAdmin (`http://localhost/phpmyadmin/`)[cite: 15].
2. Create a new database named **`arknights_db`**[cite: 15].
3. Import the provided SQL dump to automatically create the `operators` table and load initial operator data[cite: 15].
4. Verify your database credentials in **`db.php`** if needed[cite: 16]:
   ```php
   $host = 'localhost';
   $dbname = 'arknights_db';
   $user = 'root'; 
   $pass = '';
###Step 3: Accessing the GalleryOpen your web browser and navigate to the project root directory:  Plaintexthttp://localhost/Yong_arknight/index.php You will see the main Rhodes Island Archives gallery showcasing cards color-coded by rarity with E2 indicator tags.  
###Step 4: Searching and Filtering OperatorsUse the search bar at the top of the gallery to look up operators by their name.  Select a specific class from the dropdown menu (e.g., GUARD, SNIPER, MEDIC) to filter results.  Click RESET to clear filters and view all operators[cite: 20].
###Step 5: Viewing and Interacting with Operator DossiersClick on any operator card in the gallery to open their detailed dossier page (details.php).  Use the PROMOTION toggle buttons to switch between PHASE 0/1 and PHASE 2 stats/portraits[cite: 18].Scroll over the operator portrait and use your mouse wheel or click to zoom in/out of the artwork[cite: 18].Review combat skill details, icons, and archive background lore[cite: 18].
###Step 6: Recruiting New OperatorsClick the + RECRUIT OPERATOR button on the top right of the main gallery (index.php)[cite: 20].Fill out the Core Profile (Name, Rarity, Class), Base attributes/images, optional Elite 2 attributes/images, combat skills, and archive lore in add.php.  Click Confirm Recruitment to archive the new operator into the database[cite: 14].
###Step 7: Modifying or Dismissing Operator RecordsModify Data: While viewing an operator's dossier (details.php), click the MODIFY DATA button to edit existing stats, skills, or images in edit.php.  Dismiss Operator: Click the [ DISMISS OPERATOR ] link at the bottom of any operator card in the gallery to permanently remove them from the records with a confirmation check[cite: 17, 20].📄 LicenseThis project is open-source and available under the MIT License.
