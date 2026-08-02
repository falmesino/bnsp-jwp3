<div class="container">
  <div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5">

      <div class="card shadow border-0">
        <div class="card-body p-4 p-lg-5">

          <h2 class="text-center mb-4">Sign In</h2>

          <form>
            <div class="mb-3">
              <label for="email" class="form-label">
                Email address
              </label>
              <input
                type="email"
                class="form-control"
                id="email"
                placeholder="name@example.com"
                required
              >
            </div>

            <div class="mb-3">
              <label for="password" class="form-label">
                Password
              </label>
              <input
                type="password"
                class="form-control"
                id="password"
                placeholder="Password"
                required
              >
            </div>

            <div class="d-none justify-content-between align-items-center mb-4">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="remember"
                >
                <label class="form-check-label" for="remember">
                  Remember me
                </label>
              </div>

              <a href="#">Forgot password?</a>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-100"
            >
              Sign In
            </button>

            <p class="d-none text-center mt-4 mb-0">
              Don't have an account?
              <a href="#">Create one</a>
            </p>

          </form>

        </div>
      </div><!--/ .card -->
    </div><!--/ .col-12 -->
  </div><!--/ .row -->
</div><!--/ .container -->