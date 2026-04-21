const { GoogleGenerativeAI } = require("@google/generative-ai");
const fs = require('fs');
const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
const genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);

async function listModels() {
    try {
        // The SDK might not have a direct listModels method in some versions
        // but let's try a simple test with a different model string
        const model = genAI.getGenerativeModel({ model: "gemini-1.5-flash-8b" });
        const result = await model.generateContent("Hello");
        console.log(result.response.text());
    } catch (e) {
        console.error("Test Error:", e.message);
    }
}
listModels();
