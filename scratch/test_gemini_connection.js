const { GoogleGenerativeAI } = require("@google/generative-ai");
const fs = require('fs');
const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));

async function test() {
    try {
        const genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);
        const model = genAI.getGenerativeModel({ model: "gemini-1.5-flash" });
        const result = await model.generateContent("Hello, are you active?");
        console.log("SUCCESS:", result.response.text());
    } catch (e) {
        console.error("FAILED:", e.message);
    }
}
test();
