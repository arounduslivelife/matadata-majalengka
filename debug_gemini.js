const { GoogleGenerativeAI } = require("@google/generative-ai");
const fs = require('fs');
const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));

async function listModels() {
    try {
        const genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);
        const result = await genAI.listModels();
        console.log("AVAILABLE MODELS:");
        for (const model of result.models) {
            console.log(`- ${model.name}`);
        }
    } catch (e) {
        console.error("FAILED TO LIST MODELS:", e.message);
    }
}

listModels();
