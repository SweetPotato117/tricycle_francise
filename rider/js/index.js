    (function redirectRiderApp() {
      const target = 'rider_app.html';
      if (location.protocol === 'file:') {
        location.replace(target);
        return;
      }
      const current = location.origin + '/tricycle_franchise/rider/' + target;
      if (location.href !== current) {
        location.replace(target);
      }
    })();
  
