<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BmbxuPwQa2lc/FVzBcNJ7UAyJxM6wuqIj61tLrc4wSX0szH/Ev+nYRRuWlolflfl" crossorigin="anonymous">
    <title>Document</title>
</head>
<body>
    <div class="container">
        <div class="row mt-5">
            <?php if($_COOKIE['error']): ?>
                <div class="alert alert-danger">
                    <p class="h5"><?=$_COOKIE['error']?></p>
                </div>
            <?php endif; ?>
            <?php if($_COOKIE['success']): ?>
                <div class="alert alert-success">
                    <p class="h5"><?=$_COOKIE['success']?></p>
                </div>
            <?php endif; ?>
            <div class="col-sm-6 offset-sm-3">
                <form action="/parser_all.php" class="form-group" method="POST">
                    <div class="row">
                        <div class="col-10">
                            <input type="text" placeholder="max: 1690" name="pages" class="form-control">
                        </div>
                        <div class="col-2">
                            <input type="submit" class="btn btn-primary" value="Спарсить">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>