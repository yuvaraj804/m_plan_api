/* ================================
   Fetch Common Master Data (local)
   ================================ */
   async function fetchCommonData() {
    let formData = new FormData();
    // formData.append('csrf_token', csrfToken);
    try {
      if (!csrfToken) {
        alertMessage('Token missing or invalid', 'e');
        displayLoader('hide');
        return;
        }
  const res = await fetch("./api/common.php", {
       method: 'POST',
       headers: { 'X-CSRF-Token':'12345' },
       body:  formData,
       });
   
      const result = await res.json();
  
      if (!result.success || typeof result.data !== 'object') {
        console.error('Invalid data:', result);
        return;
      }
  
      const records = result.data;
  
      // Get DOM elements
      const el = id => document.getElementById(id);
      const branchSelect = el('branch_code');
      const mtList = el("mt_type");
      const equDatalist = el('equ-code');
      const equCodeInput = el('equ_code')
      const pstage = el("ps_code");
      const pref = el("priority");
      const assign_to = el("assign_to");
      const equNameDisplay = el('equ_name_display');
      let equipmentMap = {}; // code → name
      // Clear existing
      if (branchSelect) branchSelect.innerHTML = '<option disabled selected>--Select--</option>';
      if (equDatalist) equDatalist.innerHTML = '';
      if (mtList) mtList.innerHTML = '<option disabled selected>--Select--</option>';
      if (pref) pref.innerHTML = "<option disabled selected>--Select--</option>";
      if (pstage) pstage.innerHTML = '<option disabled selected>--Select--</option>';
      if (assign_to) assign_to.innerHTML = '<option disabled selected>--Select--</option>';
  
      // Populate Branch Select
      if (records.branch && branchSelect) {
        records.branch.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.code;
          opt.textContent = item.name;
          branchSelect.appendChild(opt);
        });
      }
  
      // Populate Maintenance Type datalist
      if (records.mtype && mtList) {
        records.mtype.forEach(item => {
          const opt = document.createElement("option");
          opt.value = item.code;
          opt.textContent = item.name;
          mtList.appendChild(opt);
        });
      }
  
      // Populate Equipment Datalist
      if (records.product && equDatalist) {
        equDatalist.innerHTML = '';
        records.product.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.code;
          opt.textContent = item.name;
          equDatalist.appendChild(opt);
          equipmentMap[item.code] = item.name;
        });
        setTimeout(() => {
          const prefilledCode = equCodeInput?.value.trim();
          if (prefilledCode&&equNameDisplay) {
            equNameDisplay.value = equipmentMap[prefilledCode] || '';
          }
        }, 300);
        
      }
    
      // Show equipment name in <inp> when code is selected
      if (equNameDisplay) {
        equCodeInput.addEventListener('input', () => {
          const code = equCodeInput.value;
          equNameDisplay.value = equipmentMap[code] || '';
        });
      }
  
      // Populate Pstage Select
      if (records.pstage && pstage) {
        records.pstage.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.code;
          opt.textContent = item.name;
          pstage.appendChild(opt);
        });
      }
  
      // Populate Assign To Select
      if (records.assign_to && assign_to) {
        records.assign_to.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.code;
          opt.textContent = item.name;
          assign_to.appendChild(opt);
        });
      }
  
       // Populate Priority Select
       if (records.pref && pref) {
        records.pref.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.code;
          opt.textContent = item.name;
          pref.appendChild(opt);
        });
      }
  
    } catch (err) {
      console.error('Error fetching or populating common data:', err);
    }
  }
  async function loadCurrentUser() {
    try {
        const response = await fetch('./api/get_session_info.php');
        const data = await response.json();

        if (data.success) {
            // Update the welcome message
            const welcomeElement = document.querySelector('.welcomeTxt');
            if (welcomeElement) {
                welcomeElement.textContent = `Welcome, ${data.user_name}`;
            }

            // Removed the window global variables
            // Just display the user name, no need to store in global scope
        }
    } catch (error) {
        console.error('Error loading user data:', error);
        // Fallback
        document.querySelector('.welcomeTxt').textContent = 'Welcome, User';
    }
}

loadCurrentUser();
  
  // /* ================================
  //    Populate HTML Lists from Common
  //    ================================ */
  // function populateCommonLists(records) {
  //   // Define your target elements
  //   const equList = document.getElementById("equ_code"); // Equipment datalist
  //   const mtList = document.getElementById("mt_type_list"); // Maintenance Type datalist
  //   const branchList = document.getElementById("branch_code"); // optional dropdown for branch
  
  //   // Clear old data
  //   if (equList) equList.innerHTML = "";
  //   if (mtList) mtList.innerHTML = "";
  //   if (branchList) branchList.innerHTML = "";
  
  //   // Filter by category
  //   const equipment = records.filter(r => r.category === "product");
  //   const maintenanceTypes = records.filter(r => r.category === "mtype");
  //   const branches = records.filter(r => r.category === "branch");
  
  //   // Populate Equipment datalist
  //   equipment.forEach(item => {
  //     const opt = document.createElement("option");
  //     opt.value = item.dname; // show equipment name
  //     opt.dataset.id = item.did;
  //     equList.appendChild(opt);
  //   });
  
  //   // Populate Maintenance Type datalist
  //   maintenanceTypes.forEach(item => {
  //     const opt = document.createElement("option");
  //     opt.value = item.dname;
  //     opt.dataset.id = item.did;
  //     mtList.appendChild(opt);
  //   });
  
  //   // Populate Branch dropdown (if present)
  //   branches.forEach(item => {
  //     const opt = document.createElement("option");
  //     opt.value = item.dname;
  //     opt.textContent = item.dname;
  //     opt.dataset.id = item.did;
  //     branchList?.appendChild(opt);
  //   });
  // }
  

// async function fetchRef_no() {
//   const url ="http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/get_next_rno.php";
//   // const url ='https://mocki.io/v1/1c3f53a5-7dc7-47df-b062-0bbbb439d693';
//   try {
//     const response = await fetch(url);
//     const { pstage_arr } = await response.json();
//     console.log("Reference no.", pstage_arr);

//     const pRef_input = document.getElementById("pref_no");

//     for (const key in pstage_arr) {
//       if (pstage_arr.hasOwnProperty(key)) {
//         const item = pstage_arr;
//         const refNo = String(item || key);
 

//         const pref_Val = document.createElement("value");
//         pRef_input.value =refNo;
//         // pref_Val.textContent = item.key || key;
//         pRef_input.appendChild(pref_Val);
//       }
//     }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// }

const inputField = document.getElementById("equ-code");
const equNameDatalist = document.getElementById("equ_code");

let equipmentList = [];
// async function fetchEquipment() {
//   const url =
//     "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_equipment.php";

//   try {
//     const response = await fetch(url);
//     const { equipment_mas_arr } = await response.json();
//     console.log("Equipment", equipment_mas_arr);


//     // Clear existing options (except default "select" for dropdown)
//     // assTechSelect.length = 1;
//     // equNameDatalist.innerHTML = '';
//        equipmentList = Object.entries(equipment_mas_arr).map(([key, item]) => ({
//       value: item.key || key,
//     }));
//     // for (const key in equipment_mas_arr) {
//     //   if (equipment_mas_arr.hasOwnProperty(key)) {
//     //     const item = equipment_mas_arr[key];

//     //     // For equipment name datalist (equ_name)
//     //     const optionEquip = document.createElement("option");
//     //     optionEquip.value = item.key || key;
//     //     equNameDatalist.appendChild(optionEquip);
//     //   }
//     // }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// function updateDatalist(filter) {
//  equNameDatalist.innerHTML = "";
//   const filtered = equipmentList.filter((item) =>
//     item.value.toLowerCase().includes(filter.toLowerCase())
//   );

//   filtered.forEach((item) => {
//     const option = document.createElement("option");
//     option.value = item.value;
//     equNameDatalist.appendChild(option);
//   });
// }

// // Input event handler
// inputField.addEventListener("input", () => {
//   const inputVal = inputField.value;

//   if (inputVal.length >= 2) {
//     updateDatalist(inputVal);
//   } else {
//     equNameDatalist.innerHTML = ""; // Clear list if under 2 chars
//   }
// });
    
// }

async function fetchPriority() {
  const url =
    "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_priority.php";

  try {
    const response = await fetch(url);
    const { pl_pri_array } = await response.json();
    console.log("Priority", pl_pri_array);

    const priority = document.getElementById("priority");

    for (const key in pl_pri_array) {
      if (pl_pri_array.hasOwnProperty(key)) {
        const item = pl_pri_array[key];

        const optPriority = document.createElement("option");
        optPriority.value = item.key || key;
        optPriority.textContent = item || key;
        priority.appendChild(optPriority);
      }
    }
  } catch (error) {
    console.error("Error fetching data:", error);
  }
}

// async function fetchAssign_to() {
//   const url =
//     "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_mpersons.php";

//   try {
//     const response = await fetch(url);
//     const { pstage_arr } = await response.json();
//     console.log("Assign to", pstage_arr);

//     const assTechSelect = document.getElementById("assign_to");
//     // Clear existing options (except default "select" for dropdown)
//     // assTechSelect.length = 1;
//     // assTechSelect.innerHTML = "";

//     for (const key in pstage_arr) {
//       if (pstage_arr.hasOwnProperty(key)) {
//         const item = pstage_arr[key];

//         // For technician select (ass_tech)
//         const optionTech = document.createElement("option");
//         optionTech.value = item.key || key;
//         optionTech.textContent = item || key;
//         assTechSelect.appendChild(optionTech);
//       }
//     }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// }

// async function fetchBranch() {
//   const url =
//     "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_branch.php";
//   try {
//     const response = await fetch(url);
//     const { acc_name_arr } = await response.json();
//     console.log("Branch", acc_name_arr);

//     const Branch = document.getElementById("branch_code");

//     // Clear existing options (except default "select" for dropdown)
//     // Branch.innerHTML = '';

//     for (const key in acc_name_arr) {
//       if (acc_name_arr.hasOwnProperty(key)) {
//         const item = acc_name_arr[key];

//         // For equipment name datalist (equ_name)
//         const optionBranch = document.createElement("option");
//         optionBranch.value = item.name || key;
//         // optionBranch.value = item.length;
//         optionBranch.textContent = item.key || key;
//         Branch.appendChild(optionBranch);
//       }
//     }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// }

// async function fetchP_Stage() {
//   const branch = document.getElementById("branch_code").value;

//   const url =
//     "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_pstage.php";

//   try {
//     const response = await fetch(url);
//     const { pstage_arr } = await response.json();
//     console.log("Pstage", pstage_arr);

//     const Branch = document.getElementById("ps_code");

//     // Clear existing options (except default "select" for dropdown)
//     // assTechSelect.length = 1;
//     // Branch.innerHTML = '';

//     for (const key in pstage_arr) {
//       if (pstage_arr.hasOwnProperty(key)) {
//         const item = pstage_arr[key];

//         // For equipment name datalist (equ_name)
//         const optionBranch = document.createElement("option");
//         optionBranch.value = item.key || key;
//         optionBranch.textContent = item || key;
//         Branch.appendChild(optionBranch);
//       }
//     }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// }

// async function fetchM_type() {
//   const url =
//     "http://82.112.237.214/minerva_erp_v14_dev/scripts/ajax/pmm_api/list_mtype.php";

//   try {
//     const response = await fetch(url);
//     const { pstage_arr } = await response.json();
//     console.log("Mtype", pstage_arr);

//     const mt_typeSelect = document.getElementById("mt_type");
//     // Clear existing options (except default "select" for dropdown)
//     // assTechSelect.length = 1;
//     // mt_typeSelect.innerHTML = "";

//     for (const key in pstage_arr) {
//       if (pstage_arr.hasOwnProperty(key)) {
//         const item = pstage_arr[key];

//         // For select
//         const optionM_type = document.createElement("option");
//         optionM_type.value = item.key || key;
//         optionM_type.textContent = item.key || key;
//         mt_typeSelect.appendChild(optionM_type);
//       }
//     }
//   } catch (error) {
//     console.error("Error fetching data:", error);
//   }
// }


