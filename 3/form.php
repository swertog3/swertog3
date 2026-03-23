<form action="" method="POST">
  <label for="email">E-mail</label>
  <input name="email" type="email" />
  <label for="fio">ФИО</label>
  <input name="fio" />
  <label for="year">Год</label>
  <select name="year">
    <?php 
    for ($i = 1922; $i <= 2022; $i++) {
      printf('<option value="%d">%d год</option>', $i, $i);
    }
    ?>
  </select>
  
  <input type="submit" value="ok" />
</form>
