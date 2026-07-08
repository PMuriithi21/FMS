<?php
// includes/footer.php
?>
    </div><!-- end content-body -->
</main>
</div><!-- end dashboard-layout -->
<script>
const bell = document.querySelector(".notification-btn");
const menu = document.querySelector(".notification-dropdown");

bell.addEventListener("click", function(e){
    e.stopPropagation();
    menu.classList.toggle("show");
});

document.addEventListener("click", function(){
    menu.classList.remove("show");
});

menu.addEventListener("click", function(e){
    e.stopPropagation();
});
</script>
</body>
</html>