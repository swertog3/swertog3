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
  <label for="number">Телефон</label>
  <input name="number" />
  <label for="year">Дата рождения:</label>
  <input name="year" />
  <label>Пол</label>
        <div>
          <input type="radio" id="cFiveOne" name="gender" value="cFiveOne" required> 
          <label for="cFiveOne">Муж</label>
        </div>
        <div>
          <input type="radio" id="cFiveThree" name="gender" value="cFiveThree"> 
          <label for="cFiveThree">Жен</label>
        </div>
        <div>
          <input type="radio" id="cFiveFour" name="gender" value="cFiveFour"> 
          <label for="cFiveFour">Не указан</label>
        </div>
      </div>
  <label>Любимый язык программирования</label>
        <select name="proga[]" multiple size="3"> 
          <option value="Pascal">Pascal</option> 
          <option value="C">C</option>
          <option value="Cpp">C++</option>
          <option value="JavaScript">JavaScript</option>
          <option value="PHP">PHP</option>
          <option value="Python">Python</option>
          <option value="Java">Java</option>
          <option value="Haskell">Haskell</option>
          <option value="Clojure">Clojure</option>
          <option value="Prolog">Prolog</option>
          <option value="Scala">Scala</option>
        </select>
  <label for="biography">Биография</label>
  <input name="biography" />
  <input type="checkbox" id="contract" name="contract" required> 
  <label for="contract">С контрактом ознакомлен(а)</label>
  <input type="submit" value="ok" />
</form>
