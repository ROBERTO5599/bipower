from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch()
    page = browser.new_page()
    page.goto("http://localhost:5173/")

    # Wait for the page to be fully loaded
    page.wait_for_load_state('networkidle')

    # Fill in incorrect credentials using IDs
    page.locator("#nick_name").fill("wronguser")
    page.locator("#password").fill("wrongpassword")

    # Click the login button
    page.get_by_role("button", name="Log in").click()

    # Wait for the error message to appear.
    error_message = page.locator("text=These credentials do not match our records.")
    expect(error_message).to_be_visible()

    # Take a screenshot
    page.screenshot(path="jules-scratch/verification/verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
