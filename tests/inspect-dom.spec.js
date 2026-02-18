// @ts-check
import { test } from '@playwright/test';

/**
 * DOM INSPECTOR - Find actual element selectors on production
 *
 * This will dump the HTML structure to help identify correct selectors
 */

test('Inspect auth page DOM structure', async ({ page }) => {
  console.log('\n🔍 Inspecting production page DOM...\n');

  await page.goto('/test-no-elem/');
  await page.waitForLoadState('networkidle');

  // Wait a bit more for any dynamic content
  await page.waitForTimeout(3000);

  // Get all buttons on the page
  const allButtons = await page.evaluate(() => {
    const buttons = Array.from(document.querySelectorAll('button, a[role="button"], input[type="button"], input[type="submit"]'));
    return buttons.map(btn => ({
      tag: btn.tagName,
      id: btn.id || 'NO_ID',
      className: btn.className || 'NO_CLASS',
      text: btn.textContent?.trim().slice(0, 50) || 'NO_TEXT',
      type: btn.getAttribute('type'),
    }));
  });

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('📋 ALL BUTTONS ON PAGE:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  allButtons.forEach((btn, idx) => {
    console.log(`\n${idx + 1}. <${btn.tag}>`);
    console.log(`   ID: ${btn.id}`);
    console.log(`   Class: ${btn.className}`);
    console.log(`   Text: ${btn.text}`);
    console.log(`   Type: ${btn.type || 'none'}`);
  });

  // Get all inputs on the page
  const allInputs = await page.evaluate(() => {
    const inputs = Array.from(document.querySelectorAll('input[type="email"], input[type="text"]'));
    return inputs.map(inp => ({
      tag: inp.tagName,
      id: inp.id || 'NO_ID',
      className: inp.className || 'NO_CLASS',
      placeholder: inp.getAttribute('placeholder') || 'NO_PLACEHOLDER',
      type: inp.getAttribute('type'),
    }));
  });

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('📧 ALL EMAIL/TEXT INPUTS ON PAGE:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  allInputs.forEach((inp, idx) => {
    console.log(`\n${idx + 1}. <${inp.tag}>`);
    console.log(`   ID: ${inp.id}`);
    console.log(`   Class: ${inp.className}`);
    console.log(`   Placeholder: ${inp.placeholder}`);
    console.log(`   Type: ${inp.type}`);
  });

  // Check for iframes
  const iframes = await page.evaluate(() => {
    const frames = Array.from(document.querySelectorAll('iframe'));
    return frames.map(frame => ({
      id: frame.id || 'NO_ID',
      src: frame.src || 'NO_SRC',
      name: frame.name || 'NO_NAME',
    }));
  });

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🖼️  IFRAMES ON PAGE:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  if (iframes.length === 0) {
    console.log('   No iframes found');
  } else {
    iframes.forEach((frame, idx) => {
      console.log(`\n${idx + 1}. <iframe>`);
      console.log(`   ID: ${frame.id}`);
      console.log(`   Name: ${frame.name}`);
      console.log(`   Src: ${frame.src}`);
    });
  }

  // Check if expected IDs exist
  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🎯 CHECKING EXPECTED SELECTORS:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  const expectedSelectors = [
    '#sb-email',
    '#sb-google-btn',
    '#sb-facebook-btn',
    '#sb-submit',
    '#sb-show-code',
  ];

  for (const selector of expectedSelectors) {
    const exists = await page.locator(selector).count();
    const visible = exists > 0 ? await page.locator(selector).isVisible() : false;
    console.log(`\n${selector}`);
    console.log(`   Exists: ${exists > 0 ? '✅' : '❌'} (count: ${exists})`);
    console.log(`   Visible: ${visible ? '✅' : '❌'}`);
  }

  // Search for elements by text content
  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('🔎 SEARCHING BY TEXT CONTENT:');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  const searchTexts = [
    'Продолжить через Google',
    'Продолжить через Facebook',
    'Продолжить с email',
    'Введите ваш email',
  ];

  for (const searchText of searchTexts) {
    // Try as button text
    const btnCount = await page.getByRole('button', { name: new RegExp(searchText, 'i') }).count();
    const inputCount = await page.getByPlaceholder(new RegExp(searchText, 'i')).count();

    console.log(`\n"${searchText}"`);
    console.log(`   As button: ${btnCount > 0 ? '✅ FOUND' : '❌ NOT FOUND'} (count: ${btnCount})`);
    console.log(`   As placeholder: ${inputCount > 0 ? '✅ FOUND' : '❌ NOT FOUND'} (count: ${inputCount})`);

    if (btnCount > 0) {
      // Get the actual selector
      const element = await page.evaluate((text) => {
        const btn = Array.from(document.querySelectorAll('button, a')).find(el =>
          el.textContent?.includes(text)
        );
        return btn ? {
          id: btn.id || 'NO_ID',
          className: btn.className || 'NO_CLASS',
          tagName: btn.tagName,
        } : null;
      }, searchText);

      if (element) {
        console.log(`   → Tag: ${element.tagName}`);
        console.log(`   → ID: ${element.id}`);
        console.log(`   → Class: ${element.className}`);
      }
    }
  }

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
});
