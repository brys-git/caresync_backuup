



import "./sidebar.js";

document.addEventListener('DOMContentLoaded', () => {
  const chartTargets = ['salesPurchaseChart', 'customerChart', 'salesChart'];
  if (chartTargets.some((id) => document.getElementById(id))) {
    import('./chart.js');
  }
});