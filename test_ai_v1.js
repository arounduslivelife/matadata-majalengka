const { GoogleGenerativeAI } = require("@google/generative-ai");
const fs = require('fs');
const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
// Try specifying the API version explicitly
const genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);

async function listModels() {
    try {
        // v1 instead of v1beta
        const model = genAI.getGenerativeModel({ model: "gemini-1.5-flash" }, { apiVersion: 'v1' });
        const result = await model.generateContent("Hello");
        console.log(result.response.text());
    } catch (e) {
        console.error("Test v1 Error:", e.message);
    }
}
listModels();
